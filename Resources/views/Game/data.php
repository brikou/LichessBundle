<?php
$game = $player->getGame();
$opponent = $player->getOpponent();
$playerFullHash = $player->getFullHash();
$data = array(
    'game' => array(
        'hash' => $game->getHash(),
        'started' => $game->getIsStarted(),
        'finished' => $game->getIsFinished(),
        'turns' => $game->getTurns(),
        'updatedAt' => $game->getUpdatedAt()
    ),
    'player' => array(
        'fullHash' => $playerFullHash,
        'color' => $player->getColor()
    ),
    'opponent' => array(
        'color' => $opponent->getColor(),
        'ai' => $opponent->getIsAi(),
    ),
    'socket_delay' => $parameters['lichess.socket.delay'] * 1000,
    'sync_delay' => $parameters['lichess.synchronizer.delay'] * 1000,
    'animation_delay' => 500,
    'url' => array(
        'socket' => '/socket/'.$playerFullHash.'.json',
        'move' => $view->router->generate('lichess_move', array('hash' => $playerFullHash)),
        'sync' => $view->router->generate('lichess_sync', array('hash' => $playerFullHash)),
        'table' => $view->router->generate('lichess_table', array('hash' => $playerFullHash)),
        'ai_level' => $opponent->getIsAi() ? $view->router->generate('lichess_ai_level', array('hash' => $playerFullHash)) : null
    ),
    'time' => time(),
    'i18n' => array(
        'Game Over' => 'Game Over',
        'Waiting for opponent' => 'Waiting for opponent',
        'Your turn' => 'Your turn'
    ),
    'possible_moves' => $possibleMoves
);
?>
<script type="text/javascript">var lichess_data = <?php echo json_encode($data) ?>;</script>
