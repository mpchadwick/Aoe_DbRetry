<?php

class Aoe_DbRetry_Model_Cron
{

    const ALERT_THRESHOLD_CONFIG_NODE = 'global/resources/aoe_dbretry_alert_threshold';
    const ALERT_RECIPIENTS_CONFIG_NODE = 'global/resources/aoe_dbretry_alert_recipients';
    const ERROR_IDENTIFIER = 'Unrecoverable Error';
    const ERROR_FILE_NAME = 'aoe_dbretry_unrecoverable.log';
    const DATE_FORMAT = 'yyyy-MM-ddThh'; // ISO_8601, but without the minutes and seconds

    protected $_alertThreshold;
    protected $_occurenceCount;
    protected $_fullErrorFilePath;
    protected $_currentHourForLogs;
    protected $_date;
    protected $_alertRecipients;

    public function checkAlert()
    {
        $threshold = (int)$this->_getAlertThreshold();
        if (!$threshold) {
            return;
        }

        $count = (int)$this->_getOccurenceCount();
        if ($count > $threshold) {
            $this->_sendAlert();
        }
    }

    protected function _getAlertThreshold()
    {
        if (is_null($this->_alertThreshold)) {
            $this->_alertThreshold = Mage::getConfig()->getNode(self::ALERT_THRESHOLD_CONFIG_NODE);
        }
        return $this->_alertThreshold;
    }

    protected function _getOccurenceCount()
    {
        if (is_null($this->_occurenceCount)) {
            $file = $this->_getFullErrorFilePath();
            $currentHourForLogs = $this->_getCurrentHourForLogs();
            $lines = preg_grep('/'.$currentHourForLogs.'/', file($file)); // Get the lines from the past hour
            $lines = preg_grep('/'.self::ERROR_IDENTIFIER.'/', $lines); // Only get the lines that identify a new error
            $this->_occurenceCount = count($lines);
        }
        return $this->_occurenceCount;
    }

    protected function _getFullErrorFilePath()
    {
        if (is_null($this->_fullErrorFilePath)) {
            $this->_fullErrorFilePath = Mage::getBaseDir('var') . DS . 'log' . DS . self::ERROR_FILE_NAME;
        }
        return $this->_fullErrorFilePath;
    }

    protected function _getCurrentHourForLogs()
    {
        if (is_null($this->_currentHourForLogs)) {
            $date = $this->_getDate();
            $this->_currentHourForLogs = $date->get(self::DATE_FORMAT);
        }
        return $this->_currentHourForLogs;
    }

    protected function _getDate()
    {
        if (is_null($this->_date)) {
            $this->_date = new Zend_Date();
        }
        return $this->_date;
    }

    protected function _sendAlert()
    {
        $mail = new Zend_Mail();
        $mail->setFrom(Mage::getStoreConfig('trans_email/ident_general/email'));
        $mail->addTo(Mage::getConfig()->getNode(self::ALERT_RECIPIENTS_CONFIG_NODE));
        $mail->setSubject(Mage::getStoreConfig('general/store_information/name') . ' - DB Retry Alert');
        $mail->setBodyText("There have been " . $this->_getOccurenceCount() . " unrecoverable errors in the past hour");
        $mail->send();
    }

}
