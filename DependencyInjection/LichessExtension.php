<?php

namespace Bundle\LichessBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LichessExtension extends Extension
{
    public function configLoad(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
        $loader->load('chess.xml');
        $loader->load('model.xml');
        $loader->load('blamer.xml');
        $loader->load('critic.xml');
        $loader->load('elo.xml');
        $loader->load('controller.xml');
        $loader->load('twig.xml');
        $loader->load('translation.xml');
        $loader->load('form.xml');
        $loader->load('logger.xml');
        $loader->load('cheat.xml');
        $loader->load('starter.xml');

        // Load asset helper compat
        $loader = new XmlFileLoader($container, __DIR__.'/../../../../vendor/symfony/src/Symfony/Bundle/CompatAssetsBundle/Resources/config');
        $loader->load('assets.xml');

        foreach ($configs as $config) {
            $this->doConfigLoad($config, $container);
        }
    }

    public function doConfigLoad(array $config, ContainerBuilder $container)
    {
        if(isset($config['ai']['class'])) {
            $container->setParameter('lichess.ai.class', $config['ai']['class']);
        }

        if(isset($config['translation']['remote_domain'])) {
            $container->setParameter('lichess.translation.remote_domain', $config['translation']['remote_domain']);
        }

    }

    public function prodLoad($config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
        $loader->load('prod.xml');
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return null;
    }

    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/symfony';
    }

    public function getAlias()
    {
        return 'lichess';
    }

}
