<?php

namespace App\EventSubscriber;

use App\Repository\LogsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GlobalErrorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private Environment $twig,
        private KernelInterface $kernel,
        private LogsRepository $logsRepository,
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -10], // Low priority to let others handle specific exceptions
        ];
    }


    public function onKernelException(ExceptionEvent $event): void
    {
        // En dev, on veut voir la stack trace de Symfony
        if ($this->kernel->getEnvironment() === 'dev') {
            return;
        }

        $exception = $event->getThrowable();
        $statusCode = 500;
        $template = 'error/global.html.twig';

        $user = $this->tokenStorage->getToken()?->getUser();
        $request = $this->requestStack->getCurrentRequest();

        // Gestion spécifique 404
        if ($exception instanceof NotFoundHttpException) {
            $template = 'error/404.html.twig';
            $statusCode = 404;
        } elseif ($exception instanceof HttpExceptionInterface) {
            // Autres erreurs HTTP (400, 405...) -> on les traite comme globales ou on laisse passer selon souhait
            // Ici, pour être "safe", on affiche la page globale mais avec le bon status code
            $statusCode = $exception->getStatusCode();
        } else {
            // C'est une vraie erreur critique (500)
            $this->logger->critical('Une erreur critique est survenue : ' . $exception->getMessage(), [
                'exception' => $exception,
                'trace' => $exception->getTraceAsString()
            ]);
            $template = 'error/global.html.twig';
            $statusCode = 500;
        }

        if ($statusCode !== 404) {


            $this->logsRepository->logAction(
                "ERREUR_SYSTEME",
                $user,
                $request?->getClientIp() ?? 'unknown',
                $request?->headers->get('User-Agent') ?? 'unknown',
                [
                    "route" => $request?->getUri(),
                    "message" => $exception->getMessage()
                ]
            );
        }


        $content = $this->twig->render($template, [
            'message' => 'Une erreur inattendue est survenue.', // Utilisé dans global.html.twig
        ]);

        $event->setResponse(new Response($content, $statusCode));
    }
}
