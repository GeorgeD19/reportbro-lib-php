<?php

require_once __DIR__ . '/errors.php';

class Color {
    function __construct($color = '') {
        $this->color_code = '';
        $this->transparent = true;
        $this->r = 0;
        $this->g = 0;
        $this->b = 0;

        $color = strval($color);
        if ($color) {
            if (strlen($color) == 7 && $color[0] == '#') {
                list($this->r, $this->g, $this->b) = sscanf($color, "#%02x%02x%02x");
                $this->transparent = false;
                $this->color_code = $color;
            }
        }
    }

    function is_black() {
        return ($this->r == 0 && $this->g == 0 && $this->b == 0 && !$this->transparent);
    }
}

class Parameter {
    function __construct($report, $data) {
        $this->report = $report;
        $this->id = intval($data->{'id'});
        $this->name = $data->{'name'} ? $data->{'name'} : '<unnamed>';
        $this->type = ParameterType::byName(str_replace('array', '_array', $data->{'type'}));
        if ($this->type == ParameterType::simple_array()) {
            $this->array_item_type = ParameterType::byName($data->{'arrayItemType'});
        } else {
            $this->array_item_type = ParameterType::none();
        }
        $this->eval = boolval($data->{'eval'});
        $this->nullable = boolval($data->{'nullable'});
        $this->expression = $data->{'expression'} ? $data->{'expression'} : '';
        $this->pattern = $data->{'pattern'} ? $data->{'pattern'} : '';
        $this->pattern_has_currency = (strpos($this->pattern, '$') !== -1);
        $this->is_internal = in_array($this->name, array('page_count', 'page_number'));
        $this->children = array();
        $this->fields = array();
        if ($this->type == ParameterType::_array() || $this->type == ParameterType::map()) {
            foreach ($data->{'children'} as $item) {
                $parameter = new Parameter($this->report, $item);
                if (in_array($parameter->name, $this->fields)) {
                    array_push($this->report->errors, new StandardError('errorMsgDuplicateParameterField', $parameter->id, 'name'));
                } else {
                    array_push($this->children, $parameter);
                    $this->fields[$parameter->name] = $parameter;
                }
            }
        }
    }
}

class BorderStyle {
    function __construct($data, $key_prefix = '') {
        $this->border_color = new Color(property_exists($data, $key_prefix . 'borderColor') ? $data->{$key_prefix . 'borderColor'} : '');
        $this->border_width = property_exists($data, $key_prefix . 'borderWidth') ? floatval($data->{$key_prefix . 'borderWidth'}) : 0.0;
        $this->border_all = property_exists($data, $key_prefix . 'borderAll') ? boolval($data->{$key_prefix . 'borderAll'}) : false;
        $this->border_left = $this->border_all || property_exists($data, $key_prefix . 'borderLeft') ? boolval($data->{$key_prefix . 'borderLeft'}) : false;
        $this->border_top = $this->border_all || property_exists($data, $key_prefix . 'borderLeft') ? boolval($data->{$key_prefix . 'borderTop'}) : false;
        $this->border_right = $this->border_all || property_exists($data, $key_prefix . 'borderLeft') ? boolval($data->{$key_prefix . 'borderRight'}) : false;
        $this->border_bottom = $this->border_all || property_exists($data, $key_prefix . 'borderLeft') ? boolval($data->{$key_prefix . 'borderBottom'}) : false;
    }
}

class TextStyle extends BorderStyle {
    function __construct($data, $key_prefix='') {
        parent::__construct($data, $key_prefix);
        $this->bold = property_exists($data, $key_prefix . 'bold') ? boolval($data->{$key_prefix . 'bold'}) : false;
        $this->italic = property_exists($data, $key_prefix . 'italic') ? boolval($data->{$key_prefix . 'italic'}) : false;
        $this->underline = property_exists($data, $key_prefix . 'underline') ? boolval($data->{$key_prefix . 'underline'}) : false;
        $this->strikethrough = property_exists($data, $key_prefix . 'strikethrough') ? boolval($data->{$key_prefix . 'strikethrough'}) : false;
        $this->horizontal_alignment = HorizontalAlignment::byName($data->{$key_prefix . 'horizontalAlignment'});
        $this->vertical_alignment = VerticalAlignment::byName($data->{$key_prefix . 'verticalAlignment'});
        $this->text_color = new Color(property_exists($data, $key_prefix . 'textColor') ? $data->{$key_prefix . 'textColor'} : '');
        $this->background_color = new Color($data->{$key_prefix . 'backgroundColor'});
        $this->font = property_exists($data, $key_prefix . 'font') ? $data->{$key_prefix . 'font'} : '';
        $this->font_size = property_exists($data, $key_prefix . 'fontSize') ? intval($data->{$key_prefix . 'fontSize'}) : 0;
        $this->line_spacing = property_exists($data, $key_prefix . 'lineSpacing') ? floatval($data->{$key_prefix . 'lineSpacing'}) : 0.0;
        $this->padding_left = property_exists($data, $key_prefix . 'paddingLeft') ? intval($data->{$key_prefix . 'paddingLeft'}) : 0;
        $this->padding_top = property_exists($data, $key_prefix . 'paddingTop') ? intval($data->{$key_prefix . 'paddingTop'}) : 0;
        $this->padding_right = property_exists($data, $key_prefix . 'paddingRight') ? intval($data->{$key_prefix . 'paddingRight'}) : 0;
        $this->padding_bottom = property_exists($data, $key_prefix . 'paddingBottom') ? intval($data->{$key_prefix . 'paddingBottom'}) : 0;
        $this->font_style = '';
        if ($this->bold) {
            $this->font_style .= 'B';
        }
        if ($this->italic) {
            $this->font_style .= 'I';
        }
        $this->text_align = '';
        if ($this->horizontal_alignment == HorizontalAlignment::left()) {
            $this->text_align = 'L';
        } else if ($this->horizontal_alignment == HorizontalAlignment::center()) {
            $this->text_align = 'C';
        } else if ($this->horizontal_alignment == HorizontalAlignment::right()) {
            $this->text_align = 'R';
        } else if ($this->horizontal_alignment == HorizontalAlignment::justify()) {
            $this->text_align = 'J';
        }
        $this->add_border_padding();
    }

    function get_font_style($ignore_underline = false) {
        $font_style = '';
        if ($this->bold) {
            $font_style += 'B';
        }
        if ($this->italic) {
            $font_style += 'I';
        }
        if ($this->underline && !$ignore_underline) {
            $font_style += 'U';
        }
        return $font_style;
    }

    function add_border_padding() {
        if ($this->border_left) {
            $this->padding_left += $this->border_width;
        }
        if ($this->border_top) {
            $this->padding_top += $this->border_width;
        }
        if ($this->border_right) {
            $this->padding_right += $this->border_width;
        }
        if ($this->border_bottom) {
            $this->padding_bottom += $this->border_width;
        }
    }
}
