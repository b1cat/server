<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Server\Listener;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\AfterWorkerStart;
use Hyperf\Server\Event\MainCoroutineServerStart;
use Hyperf\Server\ServerInterface;
use Hyperf\Server\ServerManager;
use Psr\Log\LoggerInterface;
use Swoole\Server\Port;

define('SWOOLE_SOCK_TCP', 1);
define('SWOOLE_SOCK_TCP6', 3);
define('SWOOLE_SOCK_UDP', 2);
define('SWOOLE_SOCK_UDP6', 4);

define('SWOW_SOCK_TCP', 16777233);
define('SWOW_SOCK_TCP6', 16777297);
define('SWOW_SOCK_UDP', 134217746);
define('SWOW_SOCK_UDP6', 134217810);

class AfterWorkerStartListener implements ListenerInterface
{
    private LoggerInterface $logger;

    public function __construct(StdoutLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [
            AfterWorkerStart::class,
            MainCoroutineServerStart::class,
        ];
    }

    /**
     * Handle the Event when the event is triggered, all listeners will
     * complete before the event is returned to the EventDispatcher.
     */
    public function process(object $event): void
    {
        /** @var AfterWorkerStart|MainCoroutineServerStart $event */
        $isCoroutineServer = $event instanceof MainCoroutineServerStart;
        if ($isCoroutineServer || $event->workerId === 0) {
            /** @var Port|\Swoole\Coroutine\Server $server */
            foreach (ServerManager::list() as [$type, $server]) {
                $listen = $server->host . ':' . $server->port;
                $type = value(function () use ($type, $server) {
                    switch ($type) {
                        case ServerInterface::SERVER_BASE:
                            $sockType = $server->type;
                            if (in_array($sockType, [SWOOLE_SOCK_TCP, SWOOLE_SOCK_TCP6, SWOW_SOCK_TCP, SWOW_SOCK_TCP6])) {
                                return 'TCP';
                            }
                            if (in_array($sockType, [SWOOLE_SOCK_UDP, SWOOLE_SOCK_UDP6, SWOW_SOCK_UDP, SWOW_SOCK_UDP6])) { 
                                return 'UDP';
                            }
                            return 'UNKNOWN';
                        case ServerInterface::SERVER_WEBSOCKET:
                            return 'WebSocket';
                        case ServerInterface::SERVER_HTTP:
                        default:
                            return 'HTTP';
                    }
                });
                $serverType = $isCoroutineServer ? ' Coroutine' : '';
                $this->logger->info(sprintf('%s%s Server listening at %s', $type, $serverType, $listen));
            }
        }
    }
}
