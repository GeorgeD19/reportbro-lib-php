<?php
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
        $this->type = ParameterType::string($data->{'type'});
        if ($this->type == ParameterType::simple_array()) {
            $this->array_item_type = ParameterType::string($data->{'arrayItemType'});
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
        if ($this->type == ParameterType::array() || $this->type == ParameterType::map()) {
            foreach ($data->{'children'} as $item) {
                $parameter = new Parameter($this->report, $item);
                if (in_array($parameter->name, $this->fields)) {
                    // $this->report->errors = array_push($this->report->errors, new Error('errorMsgDuplicateParameterField', $parameter->id, 'name'));
                } else {
                    $this->children = array_push($this->children, $parameter);
                    $this->fields[$parameter->name] = $parameter;
                }
            }
        }
    }
}


class BorderStyle {
    function __construct($data, $key_prefix = '') {
        $this->border_color = new Color($data->{$key_prefix . 'borderColor'});
        $this->border_width = floatval($data->{$key_prefix . 'borderWidth'});
        $this->border_all = boolval($data->{$key_prefix . 'borderAll'});
        $this->border_left = $this->border_all || boolval($data->{$key_prefix . 'borderLeft'});
        $this->border_top = $this->border_all || boolval($data->{$key_prefix . 'borderTop'});
        $this->border_right = $this->border_all || boolval($data->{$key_prefix . 'borderRight'});
        $this->border_bottom = $this->border_all || boolval($data->{$key_prefix . 'borderBottom'});
    }
}

class TextStyle extends BorderStyle {
    function __construct($data, $key_prefix='') {
        parent::__construct($data, $key_prefix);
        $this->bold = boolval($data->{$key_prefix . 'bold'});
        $this->italic = boolval($data->{$key_prefix . 'italic'});
        $this->underline = boolval($data->{$key_prefix . 'underline'});
        $this->strikethrough = boolval($data->{$key_prefix . 'strikethrough'});
        $this->horizontal_alignment = HorizontalAlignment::string($data->{$key_prefix . 'horizontalAlignment'});
        $this->vertical_alignment = VerticalAlignment::string($data->{$key_prefix . 'verticalAlignment'});
        $this->text_color = new Color($data->{$key_prefix . 'textColor'});
        $this->background_color = new Color($data->{$key_prefix . 'backgroundColor'});
        $this->font = $data->{$key_prefix . 'font'};
        $this->font_size = intval($data->{$key_prefix . 'fontSize'});
        $this->line_spacing = floatval($data->{$key_prefix . 'lineSpacing'});
        $this->padding_left = intval($data->{$key_prefix . 'paddingLeft'});
        $this->padding_top = intval($data->{$key_prefix . 'paddingTop'});
        $this->padding_right = intval($data->{$key_prefix . 'paddingRight'});
        $this->padding_bottom = intval($data->{$key_prefix . 'paddingBottom'});
        $this->font_style = '';
        if ($this->bold) {
            $this->font_style += 'B';
        }
        if ($this->italic) {
            $this->font_style += 'I';
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