<?php
/**
 * 2007-2015 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2015 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
namespace PrestaShopBundle\Service\Hook;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;

/**
 * This dispatcher is used to trigger hook listeners.
 *
 * The dispatch process cannot be stopped like a common EventDispatcher.
 *
 * If the event is a RenderingHookEvent, then the final result is
 * an array of contents accessed from $event->getContent().
 */
class HookDispatcher extends EventDispatcher
{
    private $renderingContent = array();
    private $propagationStoppedCalledBy = false;

    /**
     * {@inheritdoc}
     * This override will check if $event is an instance of HookEvent.
     * @throws \Exception If the Event is not HookEvent or a subclass.
     */
    public function dispatch($eventName, Event $event = null)
    {
        if ($event === null) {
            $event = new HookEvent();
        }
        if (!$event instanceof HookEvent) {
            throw new \Exception('HookDispatcher must dispatch a HookEvent subclass only. '.get_class($event).' given.');
        }
        return parent::dispatch($eventName, $event);
    }

    /**
     * {@inheritdoc}
     * This override will avoid PropagationStopped to break the dispatching process.
     * After dispatch, in case of RenderingHookEvent, the final content array will be set in event.
     */
    protected function doDispatch($listeners, $eventName, Event $event)
    {
        $this->propagationStoppedCalled = false;
        foreach ($listeners as $listener) {
            call_user_func($listener, $event, $eventName, null); // removes $this to parameters. Hooks should not have access to dispatcher
            if ($event instanceof RenderingHookEvent) {
                $listenerName = $event->popListener() ?: $listener[1];
                $this->renderingContent[$listenerName] = $event->popContent();
            }
            if ($event->isPropagationStopped()) {
                //break; // No break here to avoid a module stopping hook access to another module.
                $this->propagationStoppedCalledBy = $listener;
            }
        }
        if ($event instanceof RenderingHookEvent) {
            $event->setContent($this->renderingContent);
            $this->renderingContent = array();
        }
    }

    /**
     * Creates a HookEvent, sets its parameters, and dispatches it.
     *
     * @param $eventName The hook name.
     * @param array $parameters Hook parameters
     * @return Event The event that has been passed to each listener.
     * @throws \Exception
     */
    public function dispatchForParameters($eventName, array $parameters = array())
    {
        $event = new HookEvent();
        $event->setHookParameters($parameters);
        return $this->dispatch($eventName, $event);
    }

    /**
     * Creates a RenderingHookEvent, sets its parameters, and dispatches it. Returns the event with the response(s).
     *
     * @param $eventName The hook name.
     * @param array $parameters Hook parameters
     * @return Event The event that has been passed to each listener. Contains the responses.
     * @throws \Exception
     */
    public function renderForParameters($eventName, array $parameters = array())
    {
        $event = new RenderingHookEvent();
        $event->setHookParameters($parameters);
        return $this->dispatch($eventName, $event);
    }
}