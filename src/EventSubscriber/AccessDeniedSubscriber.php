<?php

namespace App\EventSubscriber;

use App\Repository\LogsRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class AccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage,
        private Environment $twig,
        private LogsRepository $logsRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 20],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AccessDeniedHttpException && !$exception instanceof AccessDeniedException) {
            return;
        }

        // Si l'utilisateur n'est pas connecté, on laisse Symfony gérer (redirection login)
        if (!$this->tokenStorage->getToken()?->getUser()) {
            return;
        }

        $user = $this->tokenStorage->getToken()->getUser();

        $this->logsRepository->logAction(
            "ACCESS_DENIED",
            $user,
            $this->requestStack->getCurrentRequest()->getClientIp(),
            $this->requestStack->getCurrentRequest()->headers->get('User-Agent'),
            [
                "route" => $this->requestStack->getCurrentRequest()->getUri(),
                'message' => $exception->getMessage()
            ]
        );

        // On rend la page d'erreur 403 personnalisée
        $content = $this->twig->render('error/403.html.twig');

        // On retourne une réponse 403 Forbidden explicite
        $event->setResponse(new Response($content, 403));
    }
}
