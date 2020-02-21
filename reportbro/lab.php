<?php

require 'vendor/autoload.php';

use Gerardojbaez\Money\Money;

$currencies = array('AR$'=>'ARS','Դ'=>'AMD','Afl. '=>'AWG','AU$'=>'AUD','B$'=>'BSD','BHD'=>'BHD','BDT'=>'BDT','BZ$'=>'BZD','BD$'=>'BMD','Bs'=>'BOB','KM '=>'BAM','p'=>'BWP','R$'=>'BRL','B$'=>'BND','CA$'=>'CAD','CI$'=>'KYD',
'CLP$'=>'CLP','CN¥'=>'CNY','COL$'=>'COP','₡'=>'CRC',' kn'=>'HRK','CUC$'=>'CUC','CUP$'=>'CUP','£'=>'CYP','Kč'=>'CZK','kr.'=>'DKK','RD$'=>'DOP','EC$'=>'XCD','EGP'=>'EGP','₡'=>'SVC','€ '=>'EUR','GH₵'=>'GHC','£'=>'GIP','Q'=>'GTQ','L'=>'HNL',
'HK$'=>'HKD','Ft'=>'HUF','kr'=>'ISK','₹'=>'INR','Rp'=>'IDR',' IRR'=>'IRR','J$'=>'JMD','¥'=>'JPY','JOD'=>'JOD','KSh'=>'KES','K.D.'=>'KWD','Ls'=>'LVL','LBP'=>'LBP','Lt'=>'LTL','ден '=>'MKD','RM'=>'MYR','Lm'=>'MTL','Rs'=>'MUR','MX$'=>'MXN',
'MT'=>'MZM','NPR'=>'NPR','NAƒ '=>'ANG','₪'=>'ILS','₺'=>'TRY','NZ$'=>'NZD','kr '=>'NOK','PKR'=>'PKR','S/.'=>'PEN','$U'=>'UYU','₱'=>'PHP','zł' => 'PLN','£'=>'GBP','OMR'=>'OMR','RON'=>'RON','ROL'=>'ROL','руб'=>'RUB','SAR'=>'SAR','S$'=>'SGD',
'SKK'=>'SKK','SIT'=>'SIT','R'=>'ZAR','₩'=>'KRW','E'=>'SZL','kr'=>'SEK','SFr'=>'CHF','TSh'=>'TZS','฿'=>'THB','T$ '=>'TOP','AED'=>'AED','₴'=>'UAH','$'=>'USD','VT'=>'VUV','Bs.'=>'VEF','Bs.'=>'VEB','₫'=>'VND','Z$'=>'ZWD');

$content = '$1234567.052';
$currencySymbol = '£';
foreach ($currencies as $key => $currency) {
    if ($key == $currencySymbol) {
        if (strpos($content, '$') !== false) {
            $content = moneyFormat(floatval($content), $currency);
        }
    }
}

echo $content;

// echo 'ARS:'; echo moneyFormat(1, 'ARS'); echo '\n';
// echo 'AMD:'; echo moneyFormat(1, 'AMD'); echo '\n';
// echo 'AWG:'; echo moneyFormat(1, 'AWG'); echo '\n';
// echo 'AUD:'; echo moneyFormat(1, 'AUD'); echo '\n';
// echo 'BSD:'; echo moneyFormat(1, 'BSD'); echo '\n';
// echo 'BHD:'; echo moneyFormat(1, 'BHD'); echo '\n';
// echo 'BDT:'; echo moneyFormat(1, 'BDT'); echo '\n';
// echo 'BZD:'; echo moneyFormat(1, 'BZD'); echo '\n';
// echo 'BMD:'; echo moneyFormat(1, 'BMD'); echo '\n';
// echo 'BOB:'; echo moneyFormat(1, 'BOB'); echo '\n';
// echo 'BAM:'; echo moneyFormat(1, 'BAM'); echo '\n';
// echo 'BWP:'; echo moneyFormat(1, 'BWP'); echo '\n';
// echo 'BRL:'; echo moneyFormat(1, 'BRL'); echo '\n';
// echo 'BND:'; echo moneyFormat(1, 'BND'); echo '\n';
// echo 'CAD:'; echo moneyFormat(1, 'CAD'); echo '\n';
// echo 'KYD:'; echo moneyFormat(1, 'KYD'); echo '\n';
// echo 'CLP:'; echo moneyFormat(1, 'CLP'); echo '\n';
// echo 'CNY:'; echo moneyFormat(1, 'CNY'); echo '\n';
// echo 'COP:'; echo moneyFormat(1, 'COP'); echo '\n';
// echo 'CRC:'; echo moneyFormat(1, 'CRC'); echo '\n';
// echo 'HRK:'; echo moneyFormat(1, 'HRK'); echo '\n';
// echo 'CUC:'; echo moneyFormat(1, 'CUC'); echo '\n';
// echo 'CUP:'; echo moneyFormat(1, 'CUP'); echo '\n';
// echo 'CYP:'; echo moneyFormat(1, 'CYP'); echo '\n';
// echo 'CZK:'; echo moneyFormat(1, 'CZK'); echo '\n';
// echo 'DKK:'; echo moneyFormat(1, 'DKK'); echo '\n';
// echo 'DOP:'; echo moneyFormat(1, 'DOP'); echo '\n';
// echo 'XCD:'; echo moneyFormat(1, 'XCD'); echo '\n';
// echo 'EGP:'; echo moneyFormat(1, 'EGP'); echo '\n';
// echo 'SVC:'; echo moneyFormat(1, 'SVC'); echo '\n';
// echo 'EUR:'; echo moneyFormat(1, 'EUR'); echo '\n';
// echo 'GHC:'; echo moneyFormat(1, 'GHC'); echo '\n';
// echo 'GIP:'; echo moneyFormat(1, 'GIP'); echo '\n';
// echo 'GTQ:'; echo moneyFormat(1, 'GTQ'); echo '\n';
// echo 'HNL:'; echo moneyFormat(1, 'HNL'); echo '\n';
// echo 'HKD:'; echo moneyFormat(1, 'HKD'); echo '\n';
// echo 'HUF:'; echo moneyFormat(1, 'HUF'); echo '\n';
// echo 'ISK:'; echo moneyFormat(1, 'ISK'); echo '\n';
// echo 'INR:'; echo moneyFormat(1, 'INR'); echo '\n';
// echo 'IDR:'; echo moneyFormat(1, 'IDR'); echo '\n';
// echo 'IRR:'; echo moneyFormat(1, 'IRR'); echo '\n';
// echo 'JMD:'; echo moneyFormat(1, 'JMD'); echo '\n';
// echo 'JPY:'; echo moneyFormat(1, 'JPY'); echo '\n';
// echo 'JOD:'; echo moneyFormat(1, 'JOD'); echo '\n';
// echo 'KES:'; echo moneyFormat(1, 'KES'); echo '\n';
// echo 'KWD:'; echo moneyFormat(1, 'KWD'); echo '\n';
// echo 'LVL:'; echo moneyFormat(1, 'LVL'); echo '\n';
// echo 'LBP:'; echo moneyFormat(1, 'LBP'); echo '\n';
// echo 'LTL:'; echo moneyFormat(1, 'LTL'); echo '\n';
// echo 'MKD:'; echo moneyFormat(1, 'MKD'); echo '\n';
// echo 'MYR:'; echo moneyFormat(1, 'MYR'); echo '\n';
// echo 'MTL:'; echo moneyFormat(1, 'MTL'); echo '\n';
// echo 'MUR:'; echo moneyFormat(1, 'MUR'); echo '\n';
// echo 'MXN:'; echo moneyFormat(1, 'MXN'); echo '\n';
// echo 'MZM:'; echo moneyFormat(1, 'MZM'); echo '\n';
// echo 'NPR:'; echo moneyFormat(1, 'NPR'); echo '\n';
// echo 'ANG:'; echo moneyFormat(1, 'ANG'); echo '\n';
// echo 'ILS:'; echo moneyFormat(1, 'ILS'); echo '\n';
// echo 'TRY:'; echo moneyFormat(1, 'TRY'); echo '\n';
// echo 'NZD:'; echo moneyFormat(1, 'NZD'); echo '\n';
// echo 'NOK:'; echo moneyFormat(1, 'NOK'); echo '\n';
// echo 'PKR:'; echo moneyFormat(1, 'PKR'); echo '\n';
// echo 'PEN:'; echo moneyFormat(1, 'PEN'); echo '\n';
// echo 'UYU:'; echo moneyFormat(1, 'UYU'); echo '\n';
// echo 'PHP:'; echo moneyFormat(1, 'PHP'); echo '\n';
// echo 'PLN:'; echo moneyFormat(1, 'PLN'); echo '\n';
// echo 'GBP:'; echo moneyFormat(1, 'GBP'); echo '\n';
// echo 'OMR:'; echo moneyFormat(1, 'OMR'); echo '\n';
// echo 'RON:'; echo moneyFormat(1, 'RON'); echo '\n';
// echo 'ROL:'; echo moneyFormat(1, 'ROL'); echo '\n';
// echo 'RUB:'; echo moneyFormat(1, 'RUB'); echo '\n';
// echo 'SAR:'; echo moneyFormat(1, 'SAR'); echo '\n';
// echo 'SGD:'; echo moneyFormat(1, 'SGD'); echo '\n';
// echo 'SKK:'; echo moneyFormat(1, 'SKK'); echo '\n';
// echo 'SIT:'; echo moneyFormat(1, 'SIT'); echo '\n';
// echo 'ZAR:'; echo moneyFormat(1, 'ZAR'); echo '\n';
// echo 'KRW:'; echo moneyFormat(1, 'KRW'); echo '\n';
// echo 'SZL:'; echo moneyFormat(1, 'SZL'); echo '\n';
// echo 'SEK:'; echo moneyFormat(1, 'SEK'); echo '\n';
// echo 'CHF:'; echo moneyFormat(1, 'CHF'); echo '\n';
// echo 'TZS:'; echo moneyFormat(1, 'TZS'); echo '\n';
// echo 'THB:'; echo moneyFormat(1, 'THB'); echo '\n';
// echo 'TOP:'; echo moneyFormat(1, 'TOP'); echo '\n';
// echo 'AED:'; echo moneyFormat(1, 'AED'); echo '\n';
// echo 'UAH:'; echo moneyFormat(1, 'UAH'); echo '\n';
// echo 'USD:'; echo moneyFormat(1, 'USD'); echo '\n';
// echo 'VUV:'; echo moneyFormat(1, 'VUV'); echo '\n';
// echo 'VEF:'; echo moneyFormat(1, 'VEF'); echo '\n';
// echo 'VEB:'; echo moneyFormat(1, 'VEB'); echo '\n';
// echo 'VND:'; echo moneyFormat(1, 'VND'); echo '\n';
// echo 'ZWD:'; echo moneyFormat(1, 'ZWD'); echo '\n';

