<?php

namespace Bundle\LichessBundle\Form;

use Bundle\LichessBundle\Config\FriendGameConfig;
use Bundle\LichessBundle\Config\AnybodyGameConfig;
use Bundle\LichessBundle\Config\AiGameConfig;
use Bundle\LichessBundle\Config\Persistence;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Form\FormContext;
use Symfony\Bundle\ZendBundle\Logger\Logger;

class GameConfigFormManager
{
    protected $security;
    protected $configPersistence;
    protected $formContext;
    protected $logger;
    protected $addColorHiddenField;

    public function __construct(SecurityContext $security, Persistence $configPersistence, FormContext $formContext, Logger $logger, $addColorHiddenField)
    {
        $this->security            = $security;
        $this->configPersistence   = $configPersistence;
        $this->formContext         = $formContext;
        $this->logger              = $logger;
        $this->addColorHiddenField = $addColorHiddenField;
    }

    public function createFriendForm()
    {
        $isAuthenticated = $this->security->isGranted('IS_AUTHENTICATED_REMEMBERED');
        $config = new FriendGameConfig();
        $config->fromArray($this->configPersistence->loadConfigFor('friend'));
        if(!$isAuthenticated) {
            $config->mode = 0;
        }
        $formClass = 'Bundle\LichessBundle\Form\\'.($isAuthenticated ? 'FriendWithModeGameConfigForm' : 'FriendGameConfigForm');

        return $this->createForm($formClass, $config);
    }

    public function createAnybodyForm()
    {
        $isAuthenticated = $this->security->isGranted('IS_AUTHENTICATED_REMEMBERED');
        $config = new AnybodyGameConfig();
        $config->fromArray($this->configPersistence->loadConfigFor('anybody'));
        if(!$isAuthenticated) {
            $config->modes = array(0);
        }
        $formClass = 'Bundle\LichessBundle\Form\\'.($isAuthenticated ? 'AnybodyWithModesGameConfigForm' : 'AnybodyGameConfigForm');

        return $this->createForm($formClass, $config);
    }

    public function createAiForm()
    {
        $config = new AiGameConfig();
        $config->fromArray($this->configPersistence->loadConfigFor('ai'));

        return $this->createForm('Bundle\LichessBundle\Form\AiGameConfigForm', $config);
    }

    protected function createForm($class, $config)
    {
        $form = call_user_func_array(array($class, 'create'), array($this->formContext, 'config'));

        $form->setLogger($this->logger);

        $form->setVariantChoices($config->getVariantChoices());
        $form->setTimeChoices($config->getTimeChoices());
        $form->setIncrementChoices($config->getIncrementChoices());

        if ($this->addColorHiddenField && $form instanceof GameConfigFormWithColor) {
            $form->addColorHiddenField();
        }

        $form->setData($config);

        if ($form instanceof GameConfigFormWithModeInterface) {
            $form->addModeChoices($config->getModeChoices());
        }

        return $form;
    }
}
