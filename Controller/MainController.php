<?php

namespace Bundle\LichessBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class MainController extends Controller
{

    public function indexAction($color)
    {
        return $this->render('LichessBundle:Main:index.twig', array(
            'color' => $color,
            'reverseColor' => 'white' === $color ? 'black' : 'white'
        ));
    }

    public function howManyPlayersNowAction()
    {
        $nbConnectedPlayers = $this->get('lichess_synchronizer')->getNbConnectedPlayers();
        $response = $this->createResponse($nbConnectedPlayers ?: "0");
        $response->headers->set('Content-Type', 'text/plain');
        return $response;
    }

    public function toggleSoundAction()
    {
        $session = $this->get('session');
        $attributeName = 'lichess.sound.enabled';
        $enableSound = !$session->get($attributeName);
        $session->set($attributeName, $enableSound);

        return $this->createResponse($enableSound ? 'on' : 'off');
    }

    public function localeAction($locale)
    {
        if($this->get('lichess.translation.manager')->isAvailable($locale)) {
            $this->get('session')->setLocale($locale);
            $this->get('session')->setFlash('locale_change', $locale);
        }
        $baseUrl = $this->generateUrl('lichess_homepage', array(), true);
        $localeUrl = $this->generateUrl('lichess_locale', array('locale' => $locale), true);
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if(empty($referer) || 0 != strpos($referer, $baseUrl) || 0 === strpos($referer, $localeUrl)) {
            $referer = $baseUrl;
        }
        return $this->redirect($referer);
    }

    public function localeLinksAction()
    {
        return $this->render('LichessBundle:Main:localeLinks.twig');
    }

    public function aboutAction()
    {
        return $this->render('LichessBundle:Main:about.twig');
    }

    public function exceptionAction($exception)
    {
        $code = $exception->getCode();
        if(404 == $code) {
            if($this->get('request')->isXmlHttpRequest()) {
                $response = $this->createResponse('You should not do that.');
            }
            else {
                $response = $this->render('LichessBundle:Main:notFound.twig');
            }
            $response->setStatusCode(404);
        } else {
            if ($code < 100 || $code > 599) {
                $code = 500;
            }
            if($this->get('request')->isXmlHttpRequest()) {
                $response = $this->createResponse('Something went terribly wrong.');
            }
            else {
                $response = $this->render('LichessBundle:Main:error.twig', array('code' => $code));
            }
            $response->setStatusCode($code);
        }

        return $response;
    }
}
