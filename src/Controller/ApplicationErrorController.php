<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApplicationErrorController extends AbstractController
{

    public function show(\Throwable $exception): Response
    {
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        if (!in_array($statusCode, [401, 403, 404, 500], true)) {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        return $this->createErrorResponse($statusCode);
    }


    private function createErrorResponse(int $statusCode): Response
    {
        return $this->render('errors/error.html.twig', [
            'status_code' => $statusCode,
            'message' => $this->messageFor($statusCode),
        ], new Response('', $statusCode));
    }

    private function messageFor(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_UNAUTHORIZED => 'Votre session est requise pour accéder à cette page.',
            Response::HTTP_FORBIDDEN => 'Vous n’avez pas les autorisations nécessaires pour accéder à cette page.',
            Response::HTTP_NOT_FOUND => 'La page demandée est introuvable ou n’existe plus.',
            default => 'Une erreur inattendue est survenue. Veuillez réessayer plus tard.',
        };
    }

}

