<?php
/**
 * Thai Citizen Check
 *
 * @package   Thai-Citizen-Check
 * @author    Siwat Techavoranant <keen@keendev.net>
 * @copyright 2018 Siwat Techavoranant
 */

class CitizenCheck {
    /**
     * Check validity of given identity
     * with The Bureau of Registration Administration, Department of Provincial Administration
     *
     * NOTE: cURL may have problem validating server's SSL certificate, results in exception being thrown
     * when that happens, try set $strictSSL to false to disable certificate validation
     * More info: https://stackoverflow.com/questions/24611640/curl-60-ssl-certificate-unable-to-get-local-issuer-certificate
     *
     * @param string $pid Citizen ID (13 digits)
     * @param string $dob Date of Birth e.g. 2018-12-31 or 25611231
     * @param bool   $strictSSL Enable SSL server validation
     * @return bool
     * @throws Exception
     */
    public static function check(string $pid, string $dob, bool $strictSSL = true): bool {
        // Format date
        if (strlen($dob) != 8) {
            $birthTimestamp = strtotime($dob);
            $dob = (date('Y', $birthTimestamp) + 543) . date('md', $birthTimestamp);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://idcard.bora.dopa.go.th/CheckStatus/POPStatusService.asmx/CheckDeathStatus");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('pid' => $pid, 'dob' => $dob)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $strictSSL);
        $output = curl_exec($ch);
        curl_close($ch);
        
        if (strpos($output, 'POPOut') === false) {
            throw new Exception('Unexpected message from API provider');
        } elseif (self::getStringBetween($output, '<isError>', '</isError>') === 'true') {
            // <isError>true</isError><errorDesc>ข้อมูลไม่ถูกต้อง</errorDesc>
            // Person doesn't exist
            return false;
        } elseif (self::getStringBetween($output, '<stCode>', '</stCode>') === '1') {
            // <stCode>1</stCode><stDesc>สถานะเสียชีวิต</stDesc>
            // Dead
            return false;
        } else {
            // <stCode>0</stCode><stDesc>สถานะปกติ (มีชีวิต)</stDesc>
            return true;
        }
    }
    
    protected static function getStringBetween(string $haystack, string $start, string $end) {
        $haystack = ' ' . $haystack;
        $ini = strpos($haystack, $start);
        if ($ini == 0) {
            return '';
        }
        $ini += strlen($start);
        $len = strpos($haystack, $end, $ini) - $ini;
        
        return substr($haystack, $ini, $len);
    }
    
}
