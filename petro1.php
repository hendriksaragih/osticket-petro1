<?php

require_once(INCLUDE_DIR . 'class.signal.php');
require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.ticket.php');
require_once(INCLUDE_DIR . 'class.osticket.php');
require_once(INCLUDE_DIR . 'class.config.php');
require_once(INCLUDE_DIR . 'class.format.php');
require_once('config.php');

class Petro1Plugin extends Plugin
{

    var $config_class = "Petro1PluginConfig";

    static $pluginInstance = null;

    private function getPluginInstance(?int $id)
    {
        if ($id && ($i = $this->getInstance($id)))
            return $i;

        return $this->getInstances()->first();
    }

    function bootstrap()
    {
        self::$pluginInstance = self::getPluginInstance(null);

        Signal::connect('threadentry.created', array($this, 'onThreadCreated'));

//        Signal::connect('model.updated', array($this, 'onTicketUpdated'), 'Ticket');
        Signal::connect('object.edited', array($this, 'onTicketUpdated'), 'Ticket');
    }

    function onTicketUpdated($ticket, $var)
    {
        global $cfg;
        if (!$cfg instanceof OsticketConfig) {
            error_log("Petro1 plugin called too early.");
            return;
        }

        if (!($var["type"] == "edited" && $var["key"] == "status_id")) { // ignore for update selain status
            return;
        }

        $payload = [
            'ticket_id' => $ticket->getId(),
            'ticket_number' => $ticket->getNumber(),
            'ticket_status' => $ticket->getStatus()->getName()
        ];
        $data_string = utf8_encode(json_encode($payload));
//        error_log($data_string);
        $this->sendToPetro1($data_string, "/osticket/update_status");
    }

    function onThreadCreated(ThreadEntry $entry)
    {
        global $cfg;
        if (!$cfg instanceof OsticketConfig) {
            error_log("Petro1 plugin called too early.");
            return;
        }

        $ticket = $this->getTicket($entry);
        $first_entry = $ticket->getMessages()[0];
        if ($entry->getId() == $first_entry->getId()) { // ignore for ticket created
            return;
        }

        $payload = [
            'ticket_id' => $ticket->getId(),
            'ticket_number' => $ticket->getNumber(),
//            'reply_text' => $this->format_text(Format::html2text($entry->getBody()->getClean())),
            'reply_text' => $entry->getBody()->getClean(),
            'reply_sender_name' => $this->getNameThread($entry),
            'reply_sender_email' => $this->getEmailThread($entry),
            'reply_created_at' => $entry->getCreateDate()
        ];

        $data_string = utf8_encode(json_encode($payload));
//        error_log($data_string);
        $this->sendToPetro1($data_string, "/osticket/update_reply");
    }


    function sendToPetro1($data_string, $path)
    {
        global $ost, $cfg;
        if (!$ost instanceof osTicket || !$cfg instanceof OsticketConfig) {
            error_log("Petro1 plugin called too early.");
            return;
        }
        $url = $this->getConfig(self::$pluginInstance)->get('petro1-base-url');
        $api_key = $this->getConfig(self::$pluginInstance)->get('petro1-api-key');
        if (!$url) {
            $ost->logError('Petro 1 Url not configured', 'Petro 1 Url not configured');
        } else {
            $url .= $path;
        }
        if (!$api_key) {
            $ost->logError('Petro 1 Api Key not configured', 'Petro 1 Api Key not configured');
        }

        try {
            // Setup curl
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Api-Key: ' . $api_key,
                    'Content-Length: ' . strlen($data_string))
            );

            if (curl_exec($ch) === false) {
                throw new \Exception($url . ' - ' . curl_error($ch));
            } else {
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($statusCode != '200') {
                    throw new \Exception(
                        'Error sending to: ' . $url
                        . ' Http code: ' . $statusCode
                        . ' curl-error: ' . curl_errno($ch));
                }
            }
        } catch (\Exception $e) {
            $ost->logError('Petro 1 posting issue!', $e->getMessage(), true);
            error_log('Error posting to petro 1. ' . $e->getMessage());
        } finally {
            curl_close($ch);
        }
    }

    function getNameThread(ThreadEntry $entry)
    {
        if ($entry->getStaff()) {
            return $entry->getStaff()->getFirstName() . " " . $entry->getStaff()->getLastName();
        }
        if ($entry->getUser()) {
            return $entry->getUser()->getName();
        }

        return $entry->getPoster();
    }

    function getEmailThread(ThreadEntry $entry)
    {
        if ($entry->getStaff()) {
            return $entry->getStaff()->getEmail();
        }
        if ($entry->getUser()) {
            return $entry->getUser()->getEmail();
        }

        return "-";
    }


    function getTicket(ThreadEntry $entry)
    {
        $ticket_id = Thread::objects()->filter([
            'id' => $entry->getThreadId()
        ])->values_flat('object_id')->first() [0];

        return Ticket::lookup(array(
            'ticket_id' => $ticket_id
        ));
    }

    function format_text($text)
    {
        $formatter = [
            '<' => '&lt;',
            '>' => '&gt;',
            '&' => '&amp;'
        ];
//        return str_replace(array_keys($formatter), array_values($formatter), $text);
        return $text;
    }


}
