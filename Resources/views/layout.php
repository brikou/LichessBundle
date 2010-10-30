<?php
    $view['stylesheets']->add('bundles/lichess/css/lichess.css');

    $view['javascripts']->add('bundles/lichess/js/lib.min.js');
    $view['javascripts']->add('bundles/lichess/js/ctrl.js');
    $assetsPack = $view['slots']->get('assets_pack');
    if('home' === $assetsPack) {
    }
    elseif('analyse' === $assetsPack) {
        $view['javascripts']->add('bundles/lichess/vendor/pgn4web/pgn4web.js');
        $view['javascripts']->add('bundles/lichess/js/analyse.js');
        $view['stylesheets']->add('bundles/lichess/css/analyse.css');
        $view['stylesheets']->add('bundles/lichess/vendor/pgn4web/fonts/pgn4web-fonts.css');
    }
    elseif('gamelist' === $assetsPack) {
        $view['stylesheets']->add('bundles/lichess/css/gamelist.css');
        $view['javascripts']->add('bundles/lichess/js/gamelist.js');
    }
    else {
        $view['javascripts']->add('bundles/lichess/js/game.js');
    }
    if($view['translator']->getLocale() !== 'en'):
        $view['javascripts']->add('http://static.addtoany.com/menu/locale/'.$view['translator']->getLocale().'.js');
    endif;
?>
<!DOCTYPE html>
<html lang="<?php echo $view['session']->getLocale() ?>">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="Content-Language" content="<?php echo $view['session']->getLocale() ?>">
        <title><?php $view['slots']->output('title', 'Lichess') ?> | free online Chess game<?php $view['slots']->output('title_suffix', '') ?></title>
        <meta content="Free online Chess game. Easy and fast: no registration, no ads, no flash. Play Chess with computer, friends or random opponent. OpenSource software, uses PHP 5.3, Symfony2 and JavaScript with jQuery 1.4" name="description">
        <meta content="Chess, Chess game, play Chess, online Chess, free Chess, quick Chess, anonymous Chess, opensource, PHP, JavaScript, artificial intelligence" name="keywords">
        <meta content="<?php echo $view['slots']->get('robots', 'index, follow') ?>" name="robots">
        <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon" />
        <?php echo $view['stylesheets'] ?>
    </head>
    <body class="<?php echo $view['slots']->get('body_class', 'normal') ?>" data-sound-enabled="<?php echo $view['session']->get('lichess.sound.enabled') ? 'on' : 'off' ?>" data-sound-file="<?php echo $view['assets']->getUrl('bundles/lichess/sound/alert.ogg') ?>">
        <div class="content">
            <div class="header">
                <h1><a class="site_title" href="<?php echo $view['router']->generate('lichess_homepage') ?>">Lichess</a></h1>
                <?php $view['slots']->output('baseline', '') ?>
                <div class="nb_connected_players" data-url="<?php echo $view['router']->generate('lichess_nb_players') ?>">
                    <?php echo $view['translator']->_('%nb% connected players', array('%nb%' => $view['lichess']->getNbConnectedPlayers())) ?>
                </div>
                <div class="lichess_goodies_wrap">
                    <?php $view['slots']->output('goodies', '<h2 class="postcast">New Chess variant: Chess960</h2><br /><a href="http://en.wikipedia.org/wiki/Chess960" target="_blank">Learn about Chess960</a>') ?>
                </div>
                <div class="lichess_chat_wrap">
                    <?php $view['slots']->output('chat', '') ?>
                </div>
            </div>
            <div id="lichess">
                <?php $view['slots']->output('_content') ?>
            </div>
        </div>
        <div class="footer_wrap">
            <ul class="lichess_social">
            </ul>
            <div class="footer">
                <div class="right">
                    <?php echo $view['translator']->_('Contact') ?>: <span class="js_email"></span><br />
                    <a href="<?php echo $view['router']->generate('lichess_about') ?>" target="_blank">Learn more about Lichess</a>
                </div>
                Get <a href="http://github.com/ornicar/lichess" target="_blank" title="See what's inside, fork and contribute">source code</a> or give <a href="http://lichess.org/forum/lichess-feedback" title="Having a suggestion, feature request or bug report? Let me know">feedback</a> or <a href="<?php echo $view['router']->generate('lichess_translate') ?>">help translate Lichess</a><br />
                <?php echo $view['translator']->_('Open Source software built with %php%, %symfony% and %jqueryui%', array('%php%' => 'PHP 5.3', '%symfony%' => '<a href="http://symfony-reloaded.org" target="_blank">Symfony2</a>', '%jqueryui%' => '<a href="http://jqueryui.com/" target="_blank">jQuery UI</a>')) ?><br />
            </div>
        </div>
        <div title="Come on, make my server suffer :)" class="lichess_server">
            <?php $loadAverage = sys_getloadavg() ?>
            <?php echo $view['translator']->_('Server load') ?>: <span class="value"><?php echo round(100*$loadAverage[1]) ?></span>%
        </div>
        <div id="top_menu">
            <a href="<?php echo $view['router']->generate('lichess_toggle_sound') ?>" id="sound_state" class="sound_state_<?php echo $view['session']->get('lichess.sound.enabled') ? 'on' : 'off' ?>"></a>
            <div class="lichess_language">
                <span><?php echo $view['translator']->getLocaleName() ?></span>
                <ul class="lichess_language_links">
                    <?php $localeUrl = $view['router']->generate('lichess_locale', array('locale' => '__')) ?>
                    <?php foreach($view['translator']->getOtherLocales() as $code => $name): ?>
                        <li><a lang="<?php echo $code ?>" href="<?php echo str_replace('__', $code, $localeUrl) ?>"><?php echo $name ?></a></li>
                    <?php endforeach ?>
                    <li><a href="<?php echo $view['router']->generate('lichess_translate') ?>">Help translate Lichess!</a></li>
                </ul>
            </div>
            <a class="goto_forum goto_nav blank_if_play" title="<?php echo $view['translator']->_('Talk about chess and discuss lichess features in the forum') ?>" href="<?php echo $view['router']->generate('forum_index') ?>">Forum</a>
            <a class="goto_gamelist goto_nav blank_if_play" title="<?php echo $view['translator']->_('See the games being played in real time') ?>" href="<?php echo $view['router']->generate('lichess_games') ?>">Games</a>
        </div>
        <?php echo $view['javascripts'] ?>
    </body>
</html>
