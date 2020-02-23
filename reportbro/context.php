<?php

require_once __DIR__ . '/enums.php';
require_once __DIR__ . '/errors.php';

const EVAL_DEFAULT_NAMES = array(
	"True"=> true,
	"False"=>false,
	"null"=> null,
);

class Context {
    function __construct(&$report, &$parameters, &$data) {
        $this->report = $report;
        $this->pattern_locale = $report->document_properties->pattern_locale;
        $this->pattern_currency_symbol = $report->document_properties->pattern_currency_symbol;
        $this->parameters = $parameters;
        $this->data = &$data;
        $this->data = array_merge($this->data, EVAL_DEFAULT_NAMES);
        $this->root_data = &$data;
        $this->root_data['page_number'] = 0;
        $this->root_data['page_count'] = 0;
    }

    function get_parameter($name, $parameters = null) {
        if ($parameters == null) {
            $parameters = $this->parameters;
        }
        if (array_key_exists($name, $parameters)) {
            return $parameters[$name];
        } else if (array_key_exists('__parent', $parameters)) {
            return $this->get_parameter($name, $parameters['__parent']);
        }
        return null;
    }

    function get_data($name, $data = null) {
        if ($data == null) {
            $data = $this->data;
        }
        if (in_array($name, $data)) {
            return array($data[$name], true);
        } else if ($data->{'__parent'}) {
            return $this->get_data($name, $data->{'__parent'});
        }
        return array(null, false);
    }

    function push_context($parameters, $data) {
        $parameters['__parent'] = $this->parameters;
        $this->parameters = $parameters;
        $data['__parent'] = $this->data;
        $this->data = $data;
    }

    function pop_context() {
        $parameters = $this->parameters['__parent'];
        if ($parameters == null) {
            throw new StandardError('Context.pop_context failed - no parent available');
        }
        unset($this->parameters['__parent']);
        $this->parameters = $parameters;
        $data = $this->data['__parent'];
        if ($data == null) {
            throw new StandardError('Context.pop_context failed - no parent available');
        }
        unset($this->data['__parent']);
        $this->data = $data;
    }

    function fill_parameters($expr, $object_id, $field, $pattern = null) {
        if (strpos($expr, '${') == false) {
            return $expr;
        }
        $ret = '';
        $prev_c = null;
        $parameter_length = 0;
        $parameter_index = -1;
        foreach (str_split($expr) as $i => $c) {
            if ($parameter_index == -1) {
                if ($prev_c == '$' && $c == '{') {
                    $parameter_length = 0;
                    $parameter_index = $i + 1;
                    $ret = substr($ret, 0, strlen($ret));
                } else {
                    $ret .= $c;
                }
            } else {
                if ($c == '}') {
                    $parameter_name = substr($expr, $parameter_index, $parameter_length);
                    $collection_name = null;
                    $field_name = null;
                    if (strpos($parameter_name, '.') !== false) {
                        $name_parts = explode('.', $parameter_name);
                        $collection_name = $name_parts[0];
                        $field_name = $name_parts[1];
                        $parameter = $this->get_parameter($collection_name);
                        if ($parameter == null) {
                            throw new ReportBroError(new StandardError('errorMsgInvalidExpressionNameNotDefined', $object_id, $field, $collection_name));
                        }
                    } else {
                        $parameter = $this->get_parameter($parameter_name);
                        if ($parameter == null) {
                            throw new ReportBroError(new StandardError('errorMsgInvalidExpressionNameNotDefined', $object_id, $field, $parameter_name));
                        }
                    }
                    $value = null;
                    if ($parameter->type == ParameterType::map()) {
                        $parameter = $this->get_parameter($field_name, $parameter->fields);
                        if ($parameter == null) {
                            throw new ReportBroError(new StandardError('errorMsgInvalidExpressionNameNotDefined', $object_id, $field, $parameter_name));
                        }
                        list($map_value, $parameter_exists) = $this->get_data($collection_name);
                        if ($parameter && is_object($map_value)) {
                            $value = $map_value->{$field_name};
                        }
                    } else {
                        list($value, $parameter_exists) = $this->get_data($parameter_name);
                    }
                    if (!$parameter_exists) {
                        throw new ReportBroError(new StandardError('errorMsgMissingParameterData', $object_id, $field, $parameter_name));
                    }

                    if ($value !== null) {
                        $ret .= $this->get_formatted_value($value, $parameter, $object_id, $pattern);
                    }
                    $parameter_index = -1;
                }
                $parameter_length++;
            }
            $prev_c = $c;
        }
        return $ret;
    }

    function evaluate_expression($expr, $object_id, $field) {
        if ($expr) {
            // try {
            //     $data = dict(EVAL_DEFAULT_NAMES);
            //     $expr = $this->replace_parameters($expr, $data);
            //     return simple_eval($expr, $data, $this->eval_functions);
            // } except NameNotDefined as ex:
            //     throw new ReportBroError(
            //         Error('errorMsgInvalidExpressionNameNotDefined', object_id=object_id, field=field, info=ex.name, context=expr))
            // except FunctionNotDefined as ex:
            //     // avoid possible unresolved attribute reference warning by using getattr
            //     func_name = getattr(ex, 'func_name')
            //     throw new ReportBroError(Error('errorMsgInvalidExpressionFuncNotDefined', object_id=object_id, field=field, info=func_name, context=expr))
            // except SyntaxError as ex:
            //     throw new ReportBroError(Error('errorMsgInvalidExpression', object_id=object_id, field=field, info=ex.msg, context=expr))
            // except Exception as ex:
            //     info = ex.message if hasattr(ex, 'message') else str(ex)
            //     throw new ReportBroError(Error('errorMsgInvalidExpression', object_id=object_id, field=field, info=info, context=expr))
            // }
        }
        return true;
    }

    static function strip_parameter_name($expr) {
        if ($expr) {
            return rtrim(ltrim(trim($expr),'${'),'}');
        }
        return $expr;
    }

    static function is_parameter_name($expr) {
        return ($expr && (strpos(ltrim($expr), '${') === 0) && (strpos(rtrim($expr), '}') === 0));
    }

    function get_formatted_value($value, $parameter, $object_id, $pattern = null, $is_array_item = false) {
        $rv = '';
        if ($is_array_item && $parameter->type == ParameterType::simple_array()) {
            $value_type = $parameter->array_item_type;
        } else {
            $value_type = $parameter->type;
        }
        if ($value_type == ParameterType::string()) {
            $rv = $value;
        } else if (in_array($value_type, array(ParameterType::number(), ParameterType::average(), ParameterType::sum()))) {
            if ($pattern) {
                $used_pattern = $pattern;
                $pattern_has_currency = (strpos($pattern, '$') !== false);
            } else {
                $used_pattern = $parameter->pattern;
                $pattern_has_currency = $parameter->pattern_has_currency;
            }
            if ($used_pattern) {
                try {
                    $value = $this->format_decimal($value, $used_pattern, $this->pattern_locale);
                    if ($pattern_has_currency) {
                        $value = str_replace($value, '$', $this->pattern_currency_symbol);
                    }
                    $rv = $value;
                } catch (Exception $e) {
                    $error_object_id = $pattern ? $object_id : $parameter->id;
                    throw new ReportBroError(new StandardError('errorMsgInvalidPattern', $error_object_id, 'pattern'));
                }
            } else {
                $rv = strval($value);
            }
        } else if ($value_type == ParameterType::date()) {
            $used_pattern = $pattern ? $pattern : $parameter->pattern;
            if ($used_pattern) {
                try {
                    $rv = $this->format_datetime($value, $used_pattern, $this->pattern_locale);
                } catch (Exception $e) {
                    $error_object_id = $pattern ? $object_id : $parameter->id;
                    throw new ReportBroError(new StandardError('errorMsgInvalidPattern',$error_object_id, 'pattern'));
                }
            } else {
                $rv = strval($value);
            }
        }
        return $rv;
    }

    function replace_parameters($expr, $data = null) {
        $pos = (strpos($expr, '${') !== false);
        if ($pos == -1) {
            return $expr;
        }
        $ret = '';
        $pos2 = 0;
        while ($pos != -1) {
            if ($pos != 0) {
                $ret += substr($expr, $pos2, $pos);
            }
            $pos2 = strpos($expr, '}', $pos);
            if ($pos2 != -1) {
                $parameter_name = substr($expr, $pos+2, $pos2);
                if ($data != null) {
                    if (strpos($parameter_name, '.') != -1) {
                        $name_parts = explode($parameter_name, '.');
                        $collection_name = $name_parts[0];
                        $field_name = $name_parts[1];
                        list($value, $parameter_exists) = $this->get_data($collection_name);
                        if (is_object($value)) {
                            $value = $value->{$field_name};
                        } else {
                            $value = null;
                        }
                        // use valid python identifier for parameter name
                        $parameter_name = $collection_name + '_' + $field_name;
                    } else {
                        list($value, $parameter_exists) = $this->get_data($parameter_name);
                    }
                    $data[$parameter_name] = $value;
                }
                $ret += $parameter_name;
                $pos2 += 1;
                $pos = strpos($expr, '${', $pos2);
            } else {
                $pos2 = $pos;
                $pos = -1;
            }
        }
        $ret += substr($expr, $pos2, strlen($expr));
        return $ret;
    }

    function inc_page_number() {
        $this->root_data['page_number'] += 1;
    }

    function get_page_number() {
        return $this->root_data['page_number'];
    }

    function set_page_count($page_count) {
        $this->root_data['page_count'] = $page_count;
    }

    function _get_datetime($instant) {
        $datetime = new \DateTime("now", new \DateTimeZone("UTC"));
        if ($instant == null) {
            return $datetime;
        } else if (is_int($instant) || is_float($instant)) {
            return $datetime->setTimestamp($instant);
        } else if ($this->is_time($instant)) {
            return new \DateTime($datetime->format('Y-m-d') . ' ' . $instant, new \DateTimeZone("UTC"));
        } else if ($this->is_date($instant)) {
            return new \DateTime($instant . ' ' . $datetime->format('H:i:s'), new \DateTimeZone("UTC"));
        } else if ($this->is_datetime($instant)) {
            return new \DateTime($instant, new \DateTimeZone("UTC"));
        }
        # TODO (3.x): Add an assertion/type check for this fallthrough branch:
        return $instant;
    }

    function is_datetime($instant) {
        if (DateTime::createFromFormat('Y-m-d H:i:s', $instant) !== FALSE) {
            return true;
        }
        return false;
    }

    function is_date($instant) {
        if (DateTime::createFromFormat('Y-m-d', $instant) !== FALSE) {
            return true;
        }
        return false;
    }

    function is_time($instant) {
        if (DateTime::createFromFormat('H:i:s', $instant) !== FALSE) {
            return true;
        }
        return false;
    }

    function format_datetime($datetime = null, $format = 'medium', $tzinfo = null, $locale = LC_TIME) {
        $datetime = $this->_ensure_datetime_tzinfo($this->_get_datetime($datetime), $tzinfo);
        $locale = Locale::parseLocale($locale);
        if (in_array($format, array('full', 'long', 'medium', 'short'))) {
            return str_replace("{1}", $this->format_date($datetime, $format, $locale), str_replace("{0}", $this->format_time($datetime, $format, null, $locale), str_replace("'", "", $this->get_datetime_format($format))));
        } else {
            return $datetime->format($this->get_date_format($format));
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
        if ($datetime->timezone == null) {
            $datetime::setTimezone("UTC");
        }
        if ($tzinfo != null) {
            $datetime = $datetime::setTimezone($tzinfo);
        }
        return $datetime;
    }

    function format_date($date = null, $format = 'medium', $locale = LC_TIME) {
        if ($date == null) {
            $date = new \DateTime("now", new \DateTimeZone("UTC"));
        } else if ($this->is_datetime($date)) {
            $date = $date;
        }
        $locale = Locale::parseLocale($locale);
        $pattern = $this->get_date_format($format, $locale);
        return $date->format($pattern);
    }

    function get_date_format($format = 'medium', $locale = LC_TIME) {
        $patterns = array(
            "long"=>"y 'm'. MMMM d 'd'.",
            "medium"=>'y-MM-dd',
            "full"=>"y 'm'. MMMM d 'd'., EEEE",
            "short"=>'y-MM-dd'
        );
        if (!in_array($format, $patterns)) {
            $format = "long";
        }
        return $patterns[$format];
    }

    function format_time($time = null, $format = 'medium', $tzinfo = null, $locale = LC_TIME) {
        $time = $this->_get_time($time, $tzinfo);

        $locale = Locale::parseLocale($locale);
        $pattern = $this->get_time_format($format, $locale);
        return $time->format($pattern);
    }

    function _get_time($time, $tzinfo = null) {
        $datetime = new \DateTime("now", new \DateTimeZone("UTC"));
        if ($time == null) {
            $time = $datetime;
        } else if (is_numeric($time)) {
            $time = $datetime->setTimestamp($time);
        }
        if ($time->timezone == null) {
            $time::setTimezone("UTC");
        }
        return $time;
    }

    function get_time_format($format = 'medium', $locale = LC_TIME) {
        $patterns = array(
            'medium'=>'HH:mm:ss',
            'long'=>'HH:mm:ss z',
            'full'=>'HH:mm:ss zzzz',
            'short'=>'HH:mm'
        );
        
        if (!in_array($format, $patterns)) {
            $format = "long";
        }
        return $patterns[$format];
    }

    function format_decimal($number, $format = null, $locale = LC_NUMERIC, $decimal_quantization = true) {
        $locale = Locale::parseLocale($locale);
        $fmt = numfmt_create($locale, NumberFormatter::DECIMAL );
        return numfmt_parse($fmt, $number);
    }
}