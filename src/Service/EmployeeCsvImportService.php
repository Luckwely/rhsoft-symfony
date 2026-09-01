<?php

namespace App\Service;

use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Parses an uploaded CSV file and creates one User per valid row, so an
 * enterprise that already has an employee list can bulk-import it instead
 * of adding employees one by one.
 *
 * Accepted columns (accents/case/spacing-insensitive): Prenom, Nom, Email,
 * Telephone, Adresse, Poste, Service, Role, Date embauche, Solde conge, Salaire base,
 * Genre, Date naissance.
 * Only "Prenom", "Nom" and "Email" are required.
 *
 * Shared by App\Controller\Rh\EmployeeController and App\Controller\Admin\EmployeeController.
 */
class EmployeeCsvImportService
{
    private const ROLE_CHOICES = [
        'rh' => 'ROLE_RH',
        'manager' => 'ROLE_MANAGER',
        'employe' => 'ROLE_EMPLOYE',
        'employee' => 'ROLE_EMPLOYE',
    ];

    private const REQUIRED_COLUMNS = ['prenom', 'nom', 'email'];

    public function __construct(
        private EntityManagerInterface $em,
        private SubscriptionLimitService $subscriptionLimitService,
    ) {
    }

    /**
     * @return array{success: array, errors: array, total: int}
     */
    public function import(string $filepath, Entreprise $entreprise): array
    {
        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            return $this->fatal('Impossible de lire le fichier envoyé.');
        }

        // Détection simple du séparateur (virgule ou point-virgule)
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = substr_count((string) $firstLine, ';') >= substr_count((string) $firstLine, ',') ? ';' : ',';

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false || count($header) === 0) {
            fclose($handle);
            return $this->fatal('Le fichier est vide ou illisible.');
        }
        $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]); // retire le BOM éventuel

        $columnMap = [];
        foreach (array_map([$this, 'normalizeHeaderLabel'], $header) as $index => $key) {
            if ($key !== '') {
                $columnMap[$key] = $index;
            }
        }

        foreach (self::REQUIRED_COLUMNS as $requiredColumn) {
            if (!isset($columnMap[$requiredColumn])) {
                fclose($handle);
                return $this->fatal("Colonne obligatoire manquante dans le fichier : \"$requiredColumn\". Téléchargez le modèle pour vérifier le format attendu.");
            }
        }

        $existingEmails = array_map(
            'strtolower',
            array_column(
                $this->em->getRepository(User::class)->createQueryBuilder('u')
                    ->select('u.email')
                    ->getQuery()
                    ->getScalarResult(),
                'email'
            )
        );

        $limit = $this->subscriptionLimitService->getEmployeeLimit($entreprise);
        $currentCount = $this->em->getRepository(User::class)->countByEntreprise($entreprise);

        $seenInFile = [];
        $success = [];
        $errors = [];
        $lineNumber = 1; // ligne 1 = en-tête

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            if (count(array_filter($row, static fn($v) => trim((string) $v) !== '')) === 0) {
                continue; // ligne vide, on ignore silencieusement
            }

            $data = [];
            foreach ($columnMap as $key => $index) {
                $data[$key] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            $prenom = $data['prenom'] ?? '';
            $nom = $data['nom'] ?? '';
            $email = $data['email'] ?? '';

            if ($prenom === '' || $nom === '' || $email === '') {
                $errors[] = ['line' => $lineNumber, 'message' => 'Prénom, nom et email sont obligatoires.'];
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['line' => $lineNumber, 'message' => "Adresse email invalide : \"$email\"."];
                continue;
            }

            $emailLower = strtolower($email);
            if (in_array($emailLower, $existingEmails, true)) {
                $errors[] = ['line' => $lineNumber, 'message' => "Un compte existe déjà avec l'email \"$email\"."];
                continue;
            }
            if (isset($seenInFile[$emailLower])) {
                $errors[] = ['line' => $lineNumber, 'message' => "Email en double dans le fichier : \"$email\"."];
                continue;
            }

            if ($limit !== null && $currentCount >= $limit) {
                $errors[] = ['line' => $lineNumber, 'message' => "Limite d'employés de votre abonnement atteinte ($limit). Ligne ignorée."];
                continue;
            }

            $user = new User();
            $user->setPrenom($prenom);
            $user->setNom($nom);
            $user->setEmail($email);
            $user->setTelephone(($data['telephone'] ?? '') !== '' ? $data['telephone'] : null);
            $user->setAdresse(($data['adresse'] ?? '') !== '' ? $data['adresse'] : null);
            $user->setPoste(($data['poste'] ?? '') !== '' ? $data['poste'] : null);
            $user->setService(($data['service'] ?? '') !== '' ? $data['service'] : null);

            $roleKey = strtolower($data['role'] ?? '');
            $user->setRoles([self::ROLE_CHOICES[$roleKey] ?? 'ROLE_EMPLOYE']);

            if (($data['date_embauche'] ?? '') !== '') {
                try {
                    $user->setDateEmbauche(new \DateTime($data['date_embauche']));
                } catch (\Exception) {
                    $errors[] = ['line' => $lineNumber, 'message' => "Date d'embauche invalide (\"{$data['date_embauche']}\") : employé importé sans cette date."];
                }
            }

            if (($data['solde_conge'] ?? '') !== '') {
                $normalized = str_replace(',', '.', $data['solde_conge']);
                if (is_numeric($normalized)) {
                    $user->setSoldeConge((float) $normalized);
                }
            }

            if (($data['salaire_base'] ?? '') !== '') {
                $normalized = str_replace(',', '.', $data['salaire_base']);
                if (is_numeric($normalized)) {
                    $user->setSalaireBase((float) $normalized);
                }
            }
            if ($user->getSalaireBase() === null) {
                $user->setSalaireBase($entreprise->getSalaireBaseForRoles($user->getRoles()));
            }

            $genre = strtoupper(substr($data['genre'] ?? '', 0, 1));
            if (in_array($genre, ['H', 'F'], true)) {
                $user->setGenre($genre);
            }

            if (($data['date_naissance'] ?? '') !== '') {
                try {
                    $user->setDateNaissance(new \DateTime($data['date_naissance']));
                } catch (\Exception) {
                    $errors[] = ['line' => $lineNumber, 'message' => "Date de naissance invalide (\"{$data['date_naissance']}\") : employé importé sans cette date."];
                }
            }

            $user->setEntreprise($entreprise);
            $user->setIsActive(false);
            $user->setIsVerified(false);

            $token = bin2hex(random_bytes(32));
            $user->setInvitationToken($token);
            $user->setInvitationExpiresAt(new \DateTimeImmutable('+48 hours'));

            $this->em->persist($user);

            $existingEmails[] = $emailLower;
            $seenInFile[$emailLower] = true;
            $currentCount++;

            $success[] = ['line' => $lineNumber, 'nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'user' => $user];
        }

        fclose($handle);

        if (count($success) > 0) {
            $this->em->flush();
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'total' => $lineNumber - 1,
        ];
    }

    /**
     * Builds the downloadable CSV template content (with BOM, ready for Excel).
     */
    public function buildTemplateCsv(): string
    {
        $headers = ['Prenom', 'Nom', 'Email', 'Telephone', 'Adresse', 'Poste', 'Service', 'Role', 'Date embauche', 'Solde conge', 'Salaire base', 'Genre', 'Date naissance'];
        $example1 = ['Jean', 'Rakoto', 'jean.rakoto@example.com', '0341234567', 'Antananarivo', 'Développeur', 'IT', 'employe', '2026-01-15', '2.5', '500000', 'H', '1994-03-12'];
        $example2 = ['Marie', 'Rasoa', 'marie.rasoa@example.com', '0331234567', 'Antananarivo', 'Chef de service', 'RH', 'manager', '2025-09-01', '2.5', '800000', 'F', '1990-07-24'];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers, ';');
        fputcsv($handle, $example1, ';');
        fputcsv($handle, $example2, ';');
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $content; // BOM UTF-8 pour une bonne ouverture dans Excel
    }

    private function normalizeHeaderLabel(string $label): string
    {
        $label = trim($label);
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $label);
        $label = $translit !== false ? $translit : $label;
        $label = strtolower($label);
        $label = preg_replace('/[^a-z0-9]+/', '_', $label);

        return trim((string) $label, '_');
    }

    private function fatal(string $message): array
    {
        return ['success' => [], 'errors' => [['line' => 0, 'message' => $message]], 'total' => 0];
    }
}
