# Audit de l’archive vues-fully-dynamic-fixed

L’archive contient des contrôleurs dédiés aux dashboards Super Admin et Admin, ainsi que des vues Admin/RH/Super Admin.

Le contrôleur `Admin/EmployeeController.php` contient bien des routes POST séparées pour la suppression simple (`/employees/{id}/delete`) et groupée (`/employees/bulk-delete`). Elles valident le rôle, le CSRF et l’appartenance à l’entreprise. La suppression groupée ignore le compte courant et limite les utilisateurs à la même entreprise.

La méthode `removeEmployeeAndDependencies()` supprime les entités liées Pointage, Planning, Congé, Démission, Paie, AvanceSalaire et Notification, puis neutralise plusieurs références utilisateur avant de supprimer l’utilisateur. La suppression est donc implémentée côté serveur, mais la présence et la validité des formulaires Twig doivent encore être vérifiées.

La première lecture montre également que la liste Admin conserve des commentaires indiquant un filtre actif désactivé, ce qui peut afficher des comptes inactifs. La dynamique exacte des dashboards et l’uniformité des espacements restent à vérifier dans les fichiers complets.

Côté RH, `Rh/EmployeeController.php` contient les routes de liste, embauche, import, édition, fiche et sortie, mais aucune route `delete` ni `bulkDelete`. Donc la suppression d’employés n’est pas implémentée côté RH dans cette archive, même si elle existe côté Admin.

La liste RH désactive également le filtre `is_active`, comme la liste Admin. Les deux listes peuvent donc afficher tous les comptes de l’entreprise, actifs ou inactifs, selon l’intention fonctionnelle.

Les contrôleurs `SuperAdmin/DashboardController.php` et `Admin/DashboardController.php` délèguent bien leurs données à `DashboardService` et transmettent un tableau `dashboard` au Twig. L’architecture est donc dynamique côté contrôleur, mais il faut encore vérifier si le service et les templates contiennent des constantes, libellés statiques ou graphiques alimentés par des valeurs codées en dur.

Les contrôleurs de dashboard sont dynamiques, mais le service présente deux points à contrôler : il appelle `EntrepriseRepository::getMonthlyRecurringRevenue()`, dont l’existence doit être confirmée, et il utilise `PageViewRepository` pour les visites. Le CA Super Admin est calculé à partir de `prixMois` des entreprises actives et non des salaires, ce qui est cohérent avec le besoin métier.

Le service Admin transmet `advancedAnalytics`, mais cette analyse parcourt tous les pointages historiques de l’entreprise sans filtre temporel. Les compteurs de collaborateurs et les récurrences sont donc dynamiques, mais potentiellement coûteux et non limités à une période donnée.
