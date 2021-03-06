<?php

namespace Bundle\LichessBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\ChoiceField;
use Symfony\Component\Form\TextareaField;
use Symfony\Component\Form\TextField;
use Bundle\LichessBundle\Document\Translation;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TranslationController extends Controller
{
    public function incompleteAction($locale)
    {
        $status = $this->get('lichess.translation.manager')->getTranslationStatus($locale);
        if(!$status['missing']) {
            return new Response('');
        }

        return $this->render('LichessBundle:Translation:incomplete.html.twig', array(
            'locale' => $locale,
            'status' => $status
        ));
    }

    public function indexAction()
    {
        $form = $this->get('lichess.form.translation');

        return $this->render('LichessBundle:Translation:index.html.twig', array(
            'form' => $form,
            'locale' => '__'
        ));
    }

    public function localeAction($locale)
    {
        $manager = $this->get('lichess.translation.manager');
        $translation = new Translation();
        $translation->setCode($locale);
        try {
            $translation->setMessages($manager->getMessagesWithReferenceKeys($locale));
        } catch(\InvalidArgumentException $e) {
            $translation->setMessages($manager->getEmptyMessages());
        }
        $form = $this->get('lichess.form.translation');
        $form->setData($translation);

        if ($this->get('request')->getMethod() == 'POST') {
            $form->bind($this->get('request'));
            if($form->isValid()) {
                $this->get('lichess.object_manager')->persist($translation);
                $this->get('lichess.object_manager')->flush(array('safe' => true));
                $this->get('session')->setFlash('notice', "Your translation has been submitted, thanks!\nI will review it and include it soon to the game.");

                return new RedirectResponse($this->generateUrl('lichess_translate_locale', array('locale' => $locale)));
            } else {
                $error = $translation->getYamlError();
            }
        }

        return $this->render('LichessBundle:Translation:locale.html.twig', array(
            'form' => $form,
            'locale' => $locale,
            'status' => $manager->getTranslationStatus($locale),
            'error' => isset($error) ? $error : false
        ));
    }

    public function exportAction()
    {
        $start = $this->get('request')->query->get('start', 1);
        $translations = $this->get('lichess.translation.provider')->getTranslations($start);

        return new Response(json_encode($translations), 200, array('Content-Type' => 'application/json'));
    }

    public function listAction()
    {
        $translations = $this->get('lichess.object_manager')->getRepository('LichessBundle:Translation')->createQueryBuilder()
            ->sort('createdAt', 'DESC')->getQuery()->execute();

        return $this->render('LichessBundle:Translation:list.html.twig', array(
            'translations' => $translations,
            'manager' => $this->get('lichess.translation.manager')
        ));
    }
}
