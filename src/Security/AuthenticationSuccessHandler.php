<?php

namespace App\Security;

use App\Repository\LogsRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private RouterInterface $router;
    private LogsRepository $logsRepository;

    public function __construct(RouterInterface $router, LogsRepository $logsRepository)
    {
        $this->router = $router;
        $this->logsRepository = $logsRepository;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();
        $roles = $user->getRoles();

        // Log de connexion
        $this->logsRepository->logAction(
            action: 'LOGIN',
            user: $user,
            ip: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
            details: ['roles' => $roles]
        );

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->router->generate('admin'));
        }

        if (in_array('ROLE_ASSESSEUR', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_assesseur_dashboard'));
        }

        // Default redirect for regular users or fallback
        return new RedirectResponse($this->router->generate('app_home'));
    }
}
