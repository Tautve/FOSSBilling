<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * This file is a delegate for module. Class does not extend any other class
 *
 * All methods provided in this example are optional, but function names are
 * still reserved.
 *
 */

namespace Box\Mod\Paidsupport;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public static function onBeforeClientOpenTicket(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();

        $client = $di['db']->load('Client', $params['client_id']);

        $paidSupportService = $di['mod_service']('Paidsupport');
        $paidSupportService->setDi($di);
        $paidSupportService->enoughInBalanceToOpenTicket($client);

        return true;
    }

    /**
     * @param \Box_Event $event
     * @return bool
     */
    public static function onAfterClientOpenTicket(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();

        $supportTicket = $di['db']->load('SupportTicket', $params['id']);
        $client = $di['db']->load('Client', $supportTicket->client_id);

        $paidSupportService = $di['mod_service']('Paidsupport');
        $paidSupportService->setDi($di);
        $paidSupportService->enoughInBalanceToOpenTicket($client);

        $clientBalanceService = $di['mod_service']('Client', 'Balance');
        $clientBalanceService->setDi($di);

        $message = sprintf('Paid support ticket#%d "%s" opened', $supportTicket->id, $supportTicket->subject);
        $extra = array(
            'rel_id' => $supportTicket->id,
            'type' => 'supportticket',
        );
        $clientBalanceService->deductFunds($client, $paidSupportService->getTicketPrice(), $message, $extra);

        return true;
    }

    /**
     * @return float
     */
    public function getTicketPrice()
    {
        $config = $this->di['mod_config']('Paidsupport');
        if (!isset($config['ticket_price'])){
            error_log('Paid Support ticket price is not set');
            return (float) 0;
        }
        return (float) $config['ticket_price'];
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        $config = $this->di['mod_config']('Paidsupport');
        return isset($config['error_msg']) ? $config['error_msg'] : 'Configure paid support module!';
    }

    public function enoughInBalanceToOpenTicket(\Model_Client $client)
    {
        $clientBalanceService = $this->di['mod_service']('Client', 'Balance');
        $clientBalance = $clientBalanceService->getClientBalance($client);

        if ($this->getTicketPrice() > $clientBalance){
            throw new \Box_Exception($this->getErrorMessage());
        }

        return true;
    }


}