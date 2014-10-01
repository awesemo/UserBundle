<?php



namespace Rz\UserBundle\Controller;

use Sonata\UserBundle\Controller\ResettingFOSUser1Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use FOS\UserBundle\Model\UserInterface;


class ResettingSonataUserController extends ResettingFOSUser1Controller
{
    /**
     * Request reset user password: show form
     */
    public function requestAction()
    {
        return $this->container->get('templating')->renderResponse('RzUserBundle:Resetting:request.html.twig');
    }

    /**
     * Request reset user password: submit form and send email
     */
    public function sendEmailAction()
    {
        $username = $this->container->get('request')->request->get('username');

        /** @var $user UserInterface */
        $user = $this->container->get('fos_user.user_manager')->findUserByUsernameOrEmail($username);

        if (null === $user) {
            return $this->container->get('templating')->renderResponse('RzUserBundle:Resetting:request.html.twig', array('account_error'=>'invalid_username', 'username' => $username));
        }

        if(!$user->isEnabled()) {
            return $this->container->get('templating')->renderResponse('RzUserBundle:Resetting:request.html.twig', array('account_error'=>'account_disabled', 'username' => $username));
        }

        if($user->isLocked()) {
            return $this->container->get('templating')->renderResponse('RzUserBundle:Resetting:request.html.twig', array('account_error'=>'account_locked', 'username' => $username));
        }

        if($user->isExpired() || ($user->getExpiresAt() && $user->getExpiresAt()->diff(new \DateTime()) >= 0)) {
            return $this->container->get('templating')->renderResponse('RzUserBundle:Resetting:request.html.twig', array('account_error'=>'account_expired', 'username' => $username));
        }

        if($user->isCredentialsExpired() || ($user->getCredentialsExpireAt() && $user->getCredentialsExpireAt()->diff(new \DateTime()) >= 0)) {
            return $this->container->get('templating')->renderResponse('RzUserBundle:Resetting:request.html.twig', array('account_error'=>'account_credentials_expired', 'username' => $username));
        }

        if ($user->isPasswordRequestNonExpired($this->container->getParameter('fos_user.resetting.token_ttl'))) {
            return $this->container->get('templating')->renderResponse('RzUserBundle:Resetting:passwordAlreadyRequested.html.twig');
        }

        if (null === $user->getConfirmationToken()) {
            /** @var $tokenGenerator \FOS\UserBundle\Util\TokenGeneratorInterface */
            $tokenGenerator = $this->container->get('fos_user.util.token_generator');
            $user->setConfirmationToken($tokenGenerator->generateToken());
        }

        $this->container->get('session')->set(static::SESSION_EMAIL, $this->getObfuscatedEmail($user));
        $this->container->get('fos_user.mailer')->sendResettingEmailMessage($user);
        $user->setPasswordRequestedAt(new \DateTime());
        $this->container->get('fos_user.user_manager')->updateUser($user);

        return new RedirectResponse($this->container->get('router')->generate('fos_user_resetting_check_email'));
    }

    /**
     * Tell the user to check his email provider
     */
    public function checkEmailAction()
    {
        $session = $this->container->get('session');
        $email = $session->get(static::SESSION_EMAIL);
        $session->remove(static::SESSION_EMAIL);

        if (empty($email)) {
            // the user does not come from the sendEmail action
            return new RedirectResponse($this->container->get('router')->generate('fos_user_resetting_request'));
        }

        return $this->container->get('templating')->renderResponse('RzUserBundle:Resetting:checkEmail.html.twig', array(
            'email' => $email,
        ));
    }

    /**
     * Reset user password
     */
    public function resetAction($token)
    {
        $user = $this->container->get('fos_user.user_manager')->findUserByConfirmationToken($token);

        if (null === $user) {
            throw new NotFoundHttpException(sprintf('The user with "confirmation token" does not exist for value "%s"', $token));
        }

        if (!$user->isPasswordRequestNonExpired($this->container->getParameter('fos_user.resetting.token_ttl'))) {
            return new RedirectResponse($this->container->get('router')->generate('fos_user_resetting_request'));
        }

        $form = $this->container->get('rz.user.resetting.form');
        $formHandler = $this->container->get('rz.user.resetting.form.handler');
        $process = $formHandler->process($user);

        if ($process) {
            if($user->isEnabled() &&
               !$user->isLocked() &&
               !($user->isCredentialsExpired() || ($user->getCredentialsExpireAt() && $user->getCredentialsExpireAt()->diff(new \DateTime()) >= 0))  &&
               !($user->isExpired() || ($user->getExpiresAt() && $user->getExpiresAt()->diff(new \DateTime()) >= 0)) ) {

                $this->setFlash('rz_user_success', 'resetting.flash.success');
                $response = new RedirectResponse($this->getRedirectionUrl($user));
                $this->authenticateUser($user, $response);
            } else {
                $response = new RedirectResponse($this->container->get('router')->generate('fos_user_security_login'));
                $this->setFlash('rz_user_error', 'account.flash.error');
            }
            return $response;
        }

        return $this->container->get('templating')->renderResponse('RzUserBundle:Resetting:reset.html.twig', array(
            'token' => $token,
            'form' => $form->createView(),
        ));
    }
}