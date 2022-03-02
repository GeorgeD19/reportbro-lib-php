<?php

namespace Reportbro;

function _get_datetime($instant) {
    $datetime = new \DateTime("now", new \DateTimeZone("UTC"));
    if ($instant == null) {
        return $datetime;
    } else if (is_int($instant) || is_float($instant)) {
        return $datetime->setTimestamp($instant);
    } else if (is_time($instant)) {
        return new \DateTime($datetime->format('Y-m-d') . ' ' . $instant->format('H:i:s'), new \DateTimeZone("UTC"));
    } else if (is_date($instant)) {
        return new \DateTime($instant->format('Y-m-d') . ' ' . $datetime->format('H:i:s'), new \DateTimeZone("UTC"));
    } else if (is_datetime($instant)) {
        return new \DateTime($instant, new \DateTimeZone("UTC"));
    }
    # TODO (3.x): Add an assertion/type check for this fallthrough branch:
    return $instant;
}

function is_datetime($instant) {
    if ($instant == null) {
        return false;
    }
    if ($instant->format('Y-m-d H:i:s') !== '0001-01-01 00:00:00') {
        return true;
    }
    return false;
}

function is_date($instant) {
    if ($instant == null) {
        return false;
    }
    if ($instant->format('Y-m-d') !== '0001-01-01') {
        return true;
    }
    return false;
}

function is_time($instant) {
    if ($instant == null) {
        return false;
    }
    if ($instant->format('H:i:s') !== '00:00:00') {
        return true;
    }
    return false;
}

function format_datetime($datetime = null, $format = 'medium', $tzinfo = null, $locale = LC_TIME) {
    $datetime = _ensure_datetime_tzinfo(_get_datetime($datetime), $tzinfo);
    if (in_array($format, array('full', 'long', 'medium', 'short'))) {
        return str_replace("{1}", format_date($datetime, $format, $locale), str_replace("{0}", format_time($datetime, $format, null, $locale), str_replace("'", "", get_datetime_format($format))));
    } else {
        return $datetime->format(get_date_format($format));
    }
}

function get_datetime_format($format = 'medium') {
    $patterns = array(
        "long" => "{1} 'at' {0}",
        "full" => "{1} 'at' {0}",
        "medium" => "{1}, {0}",
        "short" => "{1}, {0}"
    );
    if (!in_array($format, $patterns)) {
        $format = "long";
    }
    return $patterns[$format];
}

function _ensure_datetime_tzinfo($datetime, $tzinfo = null) {
    if (!isset($datetime->timezone)) {
        $datetime->setTimezone(new \DateTimeZone("UTC"));
    }
    // if ($tzinfo != null) {
    //     $datetime = $datetime->setTimezone(new DateTimeZone($tzinfo));
    // }
    return $datetime;
}

function format_date($date = null, $format = 'medium', $locale = LC_TIME) {
    if ($date == null) {
        $date = new \DateTime("now", new \DateTimeZone("UTC"));
    } else if (is_datetime($date)) {
        $date = $date;
    }
    $pattern = get_date_format($format, $locale);
    return $date->format($pattern);
}

function get_date_format($format = 'medium', $locale = LC_TIME) {
    // | Python | String | PHP |
    // | "d. MMMM yyyy" | 23. February 2020 | "d. F Y" |
    // | "MMMM d, y" | February 23, 2020 | "F d, Y" |
    // | "MMM d, y" | Feb 23, 2020 | "M d, Y" |
    // | "EEEE, MMMM d, y" | Sunday, February 23, 2020 | "l, F d, Y" |
    // | "M/d/yy" | 2/23/20 | "n/d/y" |

    $date_patterns = array(
        "long"=>"F d, Y",
        "medium"=>'M d, Y',
        "full"=>"l, F d, Y",
        "short"=>'n/d/y'
    );
    if (!in_array($format, $date_patterns)) {
        $format = "long";
    }
    return $date_patterns[$format];
}

function format_time($time = null, $format = 'medium', $tzinfo = null, $locale = LC_TIME) {
    $time = _get_time($time, $tzinfo);

    $pattern = get_time_format($format, $locale);
    return $time->format($pattern);
}

function _get_time($time, $tzinfo = null) {
    $datetime = new \DateTime("now", new \DateTimeZone("UTC"));
    if ($time == null) {
        $time = $datetime;
    } else if (is_numeric($time)) {
        $time = $datetime->setTimestamp($time);
    }
    if (!isset($time->timezone)) {
        $time->setTimezone(new \DateTimeZone("UTC"));
    }
    return $time;
}

function get_time_format($format = 'medium', $locale = LC_TIME) {
    $time_patterns = array(
        'medium'=>'h:mm:ss a',
        'long'=>'h:mm:ss a z',
        'full'=>'h:mm:ss a zzzz',
        'short'=>'h:mm a'
    );
    if (!in_array($format, $time_patterns)) {
        $format = "long";
    }
    return $time_patterns[$format];
}

function format_decimal($number, $format = null, $locale = LC_NUMERIC, $decimal_quantization = true) {
    $decimals = 0;
    if (strpos($format, '.') !== false) {
        $decimals = strlen(substr(strrchr($format, "."), 1));
    }

    $thousands = false;
    if (strpos($format, ',') !== false) {
        $thousands = true;
    }

    $currency = false;
    if (strpos($format, '$') !== false) {
        $currency = true;
    }

    $number = number_format(floatval($number), $decimals, '.', $thousands ? ',' : '');

    if ($currency) {
        $number = "$ " . $number;
    }

    return $number;
}
