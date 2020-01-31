<?php
namespace FlagshipWoocommerce\Helpers;

class Validation_Helper {

    public static function validateMultiEmails($emails) {
        $emails = explode(';', trim($emails));

        $invalidEmails = array_filter($emails, function($val) {
            return is_email(trim($val)) === false;
        });

        return count($invalidEmails) == 0;
    }
}