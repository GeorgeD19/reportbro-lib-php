<?php

// from .barcode128 import code128_image
require_once __DIR__ . '/context.php';
require_once __DIR__ . '/enums.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/structs.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/containers.php';

class DocElementBase {
    function __construct($report, $data) {
        $this->report = $report;
        $this->id = null;
        $this->y = property_exists($data, "y") ? intval($data->{'y'}) : 0;
        $this->render_y = 0;
        $this->render_bottom = 0;
        $this->bottom = $this->y;
        $this->height = 0;
        $this->print_if = null;
        $this->remove_empty_element = false;;
        $this->spreadsheet_hide = true;
        $this->spreadsheet_column = null;
        $this->spreadsheet_add_empty_row = false;;
        $this->first_render_element = true;
        $this->rendering_complete = false;;
        $this->predecessors = array();
        $this->successors = array();
        $this->sort_order = 1;  // sort order for elements with same 'y'-value
    }

    function is_predecessor($elem) {
        // if bottom of element is above y-coord of first predecessor we do not need to store
        // the predecessor here because the element is already a predecessor of the first predecessor
        return $this->y >= $elem->bottom and (count($this->predecessors) == 0 || $elem->bottom > $this->predecessors[0]->y);
    }

    function add_predecessor($predecessor) {
        array_push($this->predecessors, $predecessor);
        array_push($predecessor->successors, $this);
    }

    // returns true in case there is at least one predecessor which is not completely rendered yet
    function has_uncompleted_predecessor($completed_elements) {
        foreach ($this->predecessors as $predecessor) {
            if (!in_array($predecessor->id, $completed_elements) || !$predecessor->rendering_complete) {
                return true;
            }
        }   
        return false;
    }

    function get_offset_y() {
        $max_offset_y = 0;
        foreach ($this->predecessors as $predecessor) {
            $offset_y = $predecessor->render_bottom + ($this->y - $predecessor->bottom);
            if ($offset_y > $max_offset_y) {
                $max_offset_y = $offset_y;
            }
        }
        return $max_offset_y;
    }

    function clear_predecessors() {
        $this->predecessors = array();
    }

    function prepare($ctx, $pdf_doc, $only_verify) {
        return;
    }

    function is_printed($ctx) {
        if ($this->print_if) {
            return $ctx->evaluate_expression($this->print_if, $this->id, 'print_if');
        }
        return true;
    }

    function finish_empty_element($offset_y) {
        if ($this->remove_empty_element) {
            $this->render_bottom = $offset_y;
        } else {
            $this->render_bottom = $offset_y + $this->height;
        }
        $this->rendering_complete = true;
    }

    function get_next_render_element($offset_y, $container_height, $ctx, $pdf_doc) {
        $this->rendering_complete = true;
        return array(null, true);
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        return;
    }

    function render_spreadsheet($row, $col, $ctx, $renderer) {
        return array($row, $col);
    }

    function cleanup() {
        return;
    }
}

class DocElement extends DocElementBase {
    function __construct($report, $data) {
        parent::__construct($report, $data);
        $this->id = property_exists($data, 'id') ? intval($data->{'id'}) : 0;
        $this->x = property_exists($data, 'x') ? intval($data->{'x'}) : 0;
        $this->width = property_exists($data, 'width') ? intval($data->{'width'}) : 0;
        $this->height = property_exists($data, 'height') ? intval($data->{'height'}) : 0;
        $this->bottom = $this->y + $this->height;
    }

    function get_next_render_element($offset_y, $container_height, $ctx, $pdf_doc) {
        if ($offset_y + $this->height <= $container_height) {
            $this->render_y = $offset_y;
            $this->render_bottom = $offset_y + $this->height;
            $this->rendering_complete = true;
            return [$this, true];
        }
        return array(null, false);
    }

    static function draw_border($x, $y, $width, $height, $render_element_type, $border_style, $pdf_doc) {
        $pdf_doc->SetDrawColor($border_style->border_color->r, $border_style->border_color->g, $border_style->border_color->b);
        $pdf_doc->SetLineWidth($border_style->border_width);
        $border_offset = $border_style->border_width / 2;
        $border_x = $x + $border_offset;
        $border_y = $y + $border_offset;
        $border_width = $width - $border_style->border_width;
        $border_height = $height - $border_style->border_width;
        if ($border_style->border_all && $render_element_type == RenderElementType::complete()) {
            $pdf_doc->Rect($border_x, $border_y, $border_width, $border_height, 'D');
        } else {
            if ($border_style->border_left) {
                $pdf_doc->Line($border_x, $border_y, $border_x, $border_y + $border_height);
            }
            if ($border_style->border_top && in_array($render_element_type, array(RenderElementType::complete(), RenderElementType::first()))) {
                $pdf_doc->Line($border_x, $border_y, $border_x + $border_width, $border_y);
            }
            if ($border_style->border_right) {
                $pdf_doc->Line($border_x + $border_width, $border_y, $border_x + $border_width, $border_y + $border_height);
            }
            if ($border_style->border_bottom && in_array($render_element_type, array(RenderElementType::complete(), RenderElementType::last()))) {
                $pdf_doc->Line($border_x, $border_y + $border_height, $border_x + $border_width, $border_y + $border_height);
            }
        }
    }
}

class ImageElement extends DocElement {
    function __construct($report, $data) {
        parent::__construct($report, $data);
        $this->source = property_exists($data, 'source') ? $data->{'source'} : '';
        $this->image = property_exists($data, 'image') ? $data->{'image'} : '';
        $this->image_filename = property_exists($data, 'imageFilename') ? $data->{'imageFilename'} : '';
        $this->horizontal_alignment = HorizontalAlignment::byName($data->{'horizontalAlignment'});
        $this->vertical_alignment = VerticalAlignment::byName($data->{'verticalAlignment'});
        $this->background_color = new Color($data->{'backgroundColor'});
        $this->print_if = property_exists($data, 'printIf') ? $data->{'printIf'} : '';
        $this->remove_empty_element = boolval($data->{'removeEmptyElement'});
        $this->link = property_exists($data, 'link') ? $data->{'link'} : '';
        $this->spreadsheet_hide = boolval($data->{'spreadsheet_hide'});
        $this->spreadsheet_column = intval($data->{'spreadsheet_column'});
        $this->spreadsheet_add_empty_row = boolval($data->{'spreadsheet_addEmptyRow'});
        $this->image_key = null;
        $this->image_type = null;
        $this->image_fp = null;
    }

    function prepare($ctx, $pdf_doc, $only_verify) {
        if ($this->image_key) {
            return;
        }
        $img_data_b64 = null;
        $is_url = false;;
        if ($this->source) {
            $source_parameter = $ctx->get_parameter(Context::strip_parameter_name($this->source));
            if ($source_parameter) {
                if ($source_parameter->type == ParameterType::string()) {
                    list($this->image_key, $parameter_exists) = $ctx->get_data($source_parameter->name);
                    $is_url = true;
                } else if ($source_parameter->type == ParameterType::image()) {
                    // image is available as base64 encoded or
                    // file object (only possible if report data is passed directly from python code
                    // and not via web request)
                    list($img_data, $parameter_exists) = $ctx->get_data($source_parameter->name);
//                     if isinstance(img_data, BufferedReader) or\
//                             (PY2 and isinstance(img_data, file)):
//                         $this->image_fp = img_data
//                         pos = img_data.name.rfind('.')
//                         $this->image_type = img_data.name[pos+1:] if pos != -1 else ''
//                     else if isinstance(img_data, basestring):
                        $img_data_b64 = $img_data;
                } else {
                    throw new ReportBroError(new StandardError('errorMsgInvalidImageSourceParameter', $this->id, 'source'));
                }
            } else {
                $source = trim($this->source);
                if (substr($source, 0, 2) == '${' && substr($source, -1) == '}') {
                    throw new ReportBroError(new StandardError('errorMsgMissingParameter', $this->id, 'source'));
                }
                $this->image_key = $this->source;
                $is_url = true;
            }
        }
        
        if ($img_data_b64 == null && !$is_url && $this->image_fp == null) {
            if ($this->image_filename and $this->image) {
                // static image base64 encoded within image element
                $img_data_b64 = $this->image;
                $this->image_key = $this->image_filename;
            }
        }

        if ($img_data_b64) {
            preg_match('~^data:image/(.+);base64,~', $img_data_b64, $m);
            if (!$m) {
                throw new ReportBroError(new StandardError('errorMsgInvalidImage', $this->id, 'source'));
            }
            $this->image_type = strtolower($m[1]);
            $image = preg_replace('~^data:image/(.+);base64,~', '', $img_data_b64);
            $img_data = base64_decode($image);
            $this->image_fp = $img_data_b64;
            // $this->image_fp = BytesIO($img_data);
        } else if ($is_url) {
            if (!($this->image_key && (strpos($this->image_key, "http://") === 0 || strpos($this->image_key, "https://") === 0))) {
                throw new ReportBroError(new StandardError('errorMsgInvalidImageSource', $this->id, 'source'));
            }
            $pos = strrpos($this->image_key, '.');
            $this->image_type = $pos != -1 ? substr($this->image_key, $pos+1) : '';
        }

        if ($this->image_type != null) {
            if (!in_array($this->image_type, array('png', 'jpg', 'jpeg'))) {
                throw new ReportBroError(new StandardError('errorMsgUnsupportedImageType', $this->id, 'source'));
            }
            if (!$this->image_key) {
                $this->image_key = 'image_' + strval($this->id) + '.' + $this->image_type;
            }
        }
        $this->image = null;

        if ($this->link) {
            $this->link = $ctx->fill_parameters($this->link, $this->id, 'link');
        }
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        $x = $this->x + $container_offset_x;
        $y = $this->render_y + $container_offset_y;
        if (!$this->background_color->transparent) {
            $pdf_doc->SetFillColor($this->background_color->r, $this->background_color->g, $this->background_color->b);
            $pdf_doc->Rect($x, $y, $this->width, $this->height, 'F');
        }
        if ($this->image_key) {
            try {
                $image = preg_replace('~^data:image/(.+);base64,~', '', $this->image_fp);
                $img_data = base64_decode($image);

                list($image_width, $image_height) = getimagesizefromstring($img_data);
                $ratio = $image_width / $image_height;
                $ratio_new = $this->width / $this->height;

                if ($image_width >= $this->width && $image_height >= $this->height) {
                    $size = $this->height;
                    if ($this->width > $this->height) {
                        $size = $this->width;
                    }
                } else if ($image_width >= $this->width) {
                    $size = $this->width;
                } else if ($image_height >= $this->height) {
                    $size = $this->height;
                } else {
                    $size = $image_height;
                    if ($image_width > $image_height) {
                        $size = $image_width;
                    }
                }

                $target_width = $target_height = min($size, max($image_width, $image_height));
                if ($ratio_new > $ratio) {
                    $target_width = $target_height * $ratio;
                    if ($target_width > $image_width) {
                        $target_width = $image_width;
                        $target_height = $image_height;
                    }
                } else {
                    $target_height = $target_width / $ratio;
                    if ($target_height > $image_height) {
                        $target_width = $image_width;
                        $target_height = $image_height;
                    }
                }

                
                $x2 = $x + $this->width;
                $y2 = $y + $this->height;

                $image_x = $x;
                switch ($this->horizontal_alignment) {
                    case HorizontalAlignment::center():
                        $image_x = ($x + ($x2 - $target_width)) / 2;
                    break;
                    case HorizontalAlignment::right():
                        $image_x = $x2 - $target_width;
                    break;
                }

                $image_y = $y;
                switch ($this->vertical_alignment) {
                    case VerticalAlignment::middle():
                        $image_y = ($y + ($y2 - $target_height)) / 2;
                    break;
                    case VerticalAlignment::bottom():
                        $image_y = $y2 - $target_height;
                    break;
                }

                $image = explode(',', $this->image_fp, 2);
                $picture = 'data://text/plain;base64,' . $image[1];
                $pdf_doc->Image($picture, $image_x, $image_y, $target_width, $target_height, $this->image_type);
            } catch (Exception $e) {
                throw new ReportBroError(new StandardError('errorMsgLoadingImageFailed', $this->id, $this->source ? 'source' : 'image'));
            }
        }

        if ($this->link) {
            // horizontal and vertical alignment of image within given width and height
            // by keeping original image aspect ratio
            $offset_x = $offset_y = 0;
            if ($image_width <= $this->width && $image_height <= $this->height) {
                list($image_display_width, $image_display_height) = array($image_width, $image_height);
            } else {
                $size_ratio = $image_width / $image_height;
                $tmp = $this->width / $size_ratio;
                if ($tmp <= $this->height) {
                    $image_display_width = $this->width;
                    $image_display_height = $tmp;
                } else {
                    $image_display_width = $this->height * $size_ratio;
                    $image_display_height = $this->height;
                }
            }
            if ($this->horizontal_alignment == HorizontalAlignment::center()) {
                $offset_x = ($this->width - $image_display_width) / 2;
            } else if ($this->horizontal_alignment == HorizontalAlignment::right()) {
                $offset_x = $this->width - $image_display_width;
            }
            if ($this->vertical_alignment == VerticalAlignment::middle()) {
                $offset_y = ($this->height - $image_display_height) / 2;
            } else if ($this->vertical_alignment == VerticalAlignment::bottom()) {
                $offset_y = $this->height - $image_display_height;
            }
            $pdf_doc->Link($x + $offset_x, $y + $offset_y, $image_display_width, $image_display_height, $this->link);
        }
    }

    function render_spreadsheet($row, $col, $ctx, $renderer) {
        if ($this->image_key) {
            if ($this->spreadsheet_column) {
                $col = $this->spreadsheet_column - 1;
            }
            $renderer->insert_image($row, $col, $this->image_key, $this->width);
            $row += $this->spreadsheet_add_empty_row ? 2 : 1;
            $col += 1;
        }
        return array($row, $col);
    }

    function cleanup() {
        if ($this->image_key) {
            $this->image_key = null;
        }
    }
}

class BarCodeElement extends DocElement {
    function __construct($report, $data) {
        parent::__construct($report, $data);
        $this->content = property_exists($data, 'content') ? $data->{'content'} : '';
        $this->format = property_exists($data, 'format') ? strtolower($data->{'format'}) : '';
        if ($this->format != 'code128') {
            throw new Exception('AssertionError');
        }
        $this->display_value = boolval($data->{'displayValue'});
        $this->print_if = property_exists($data, 'printIf') ? $data->{'printIf'} : '';
        $this->remove_empty_element = boolval($data->{'removeEmptyElement'});
        $this->spreadsheet_hide = boolval($data->{'spreadsheet_hide'});
        $this->spreadsheet_column = intval($data->{'spreadsheet_column'});
        $this->spreadsheet_colspan = intval($data->{'spreadsheet_colspan'});
        $this->spreadsheet_add_empty_row = boolval($data->{'spreadsheet_addEmptyRow'});
        $this->image_key = null;
        $this->image_height = $this->display_value ? $this->height - 22 : $this->height;
    }

    function is_printed($ctx) {
        if (!$this->content) {
            return false;
        }
        return DocElementBase::is_printed($ctx);
    }

    function prepare($ctx, $pdf_doc, $only_verify) {
        if ($this->image_key) {
            return;
        }
        $this->content = $ctx->fill_parameters($this->content, $this->id, 'content');
        if ($this->content) {
            // try {
            //     $img = code128_image($this->content, $this->image_height, 2, false);
            // } catch (Exception $e) {
            //     throw new ReportBroError(new StandardError('errorMsgInvalidBarCode', $this->id, 'content'));
            // }
            if (!$only_verify) {
                // with tempfile.NamedTemporaryFile(delete=false;, suffix='.png') as f:
                //     $img->save($f->name);
                //     $this->image_key = $f->name;
                //     $this->width = $img->width;
            }
        }
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        $x = $this->x + $container_offset_x;
        $y = $this->render_y + $container_offset_y;
        if ($this->image_key) {
            $pdf_doc->Image($this->image_key, $x, $y, $this->width, $this->image_height);
            if ($this->display_value) {
                $pdf_doc->SetFont('courier', 'B', 18);
                $pdf_doc->SetTextColor(0, 0, 0);
                $content_width = $pdf_doc->GetStringWidth($this->content);
                $offset_x = ($this->width - $content_width) / 2;
                $pdf_doc->Text($x + $offset_x, $y + $this->image_height + 20, $this->content);
            }
        }
    }

    function render_spreadsheet($row, $col, $ctx, $renderer) {
        if ($this->content) {
            $cell_format = (object)array();
            if ($this->spreadsheet_column) {
                $col = $this->spreadsheet_column - 1;
            }
            $renderer->write($row, $col, $this->spreadsheet_colspan, $this->content, $cell_format, $this->width);
            $row += $this->spreadsheet_add_empty_row ? 2 : 1;
            $col += 1;
        }
        return array($row, $col);
    }

    function cleanup() {
        if ($this->image_key) {
            // os.unlink($this->image_key)
            $this->image_key = null;
        }
    }
}


class LineElement extends DocElement {
    function __construct($report, $data) {
        parent::__construct($report, $data);
        $this->color = new Color($data->{'color'});
        $this->print_if = property_exists($data, 'printIf') ? $data->{'printIf'} : '';
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        $pdf_doc->SetDrawColor($this->color->r, $this->color->g, $this->color->b);
        $pdf_doc->SetLineWidth($this->height);
        $x = $this->x + $container_offset_x;
        $y = $this->render_y + $container_offset_y + ($this->height / 2);
        $pdf_doc->Line($x, $y, $x + $this->width, $y);
    }
}

class PageBreakElement extends DocElementBase {
    function __construct($report, $data) {
        parent::__construct($report, $data);
        $this->id = property_exists($data, 'id') ? intval($data->{'id'}) : 0;
        $this->x = 0;
        $this->width = 0;
        $this->sort_order = 0;  // sort order for elements with same 'y'-value, render page break before other elements
    }
}

class TextElement extends DocElement {
    function __construct($report, $data) {
        parent::__construct($report, $data);
        $this->content = property_exists($data, 'content') ? $data->{'content'} : '';
        $this->eval = boolval($data->{'eval'});
        if ($data->{'styleId'}) {
            $this->style = $report->styles[intval($data->{'styleId'})];
            if ($this->style == null) {
                throw new Exception('Style for text element ' . $this->id . ' not found');
            }
        } else {
            $this->style = new TextStyle($data);
        }
        $this->print_if = property_exists($data, 'printIf') ? $data->{'printIf'} : '';
        $this->pattern = property_exists($data, 'pattern') ? $data->{'pattern'} : '';
        $this->link = property_exists($data, 'link') ? $data->{'link'} : '';
        $this->cs_condition = $data->{'cs_condition'};
        if ($this->cs_condition) {
            if (property_exists($data, 'cs_styleId')) {
                $this->conditional_style = $report->styles[intval($data->{'cs_styleId'})];
                if ($this->conditional_style == null) {
                    throw new Exception('Conditional style for text element ' . $this->id . ' not found');
                }
            } else {
                $this->conditional_style = new TextStyle($data, 'cs_');
            }
        } else {
            $this->conditional_style = null;
        }
        if ($this instanceof TableTextElement) {
            $this->remove_empty_element = false;
            $this->always_print_on_same_page = false;
        } else {
            $this->remove_empty_element = boolval($data->{'removeEmptyElement'});
            $this->always_print_on_same_page = boolval($data->{'alwaysPrintOnSamePage'});
        }
        $this->height = intval($data->{'height'});
        $this->spreadsheet_hide = property_exists($data, 'spreadsheet_hide') ? boolval($data->{'spreadsheet_hide'}) : false;
        $this->spreadsheet_column = property_exists($data, 'spreadsheet_column') ? intval($data->{'spreadsheet_column'}) : 0;
        $this->spreadsheet_colspan = property_exists($data, 'spreadsheet_colspan') ? intval($data->{'spreadsheet_colspan'}) : 0;
        $this->spreadsheet_add_empty_row = property_exists($data, 'spreadsheet_addEmptyRow') ? boolval($data->{'spreadsheet_addEmptyRow'}) : false;
        $this->text_height = 0;
        $this->line_index = -1;
        $this->line_height = 0;
        $this->lines_count = 0;
        $this->text_lines = null;
        $this->used_style = null;
        $this->space_top = 0;
        $this->space_bottom = 0;
        $this->total_height = 0;
        $this->spreadsheet_cell_format = null;
        $this->spreadsheet_cell_format_initialized = false;
    }

    function is_printed($ctx) {
        if ($this->remove_empty_element && count($this->text_lines) == 0) {
            return false;
        }
        return DocElementBase::is_printed($ctx);
    }

    function prepare($ctx, $pdf_doc, $only_verify) {
        if ($this->eval) {
            $content = $ctx->evaluate_expression($this->content, $this->id, 'content');
            if ($this->pattern) {
                if (is_numeric($content)) {
                    try {
                        $content = format_decimal($content, $this->pattern, $ctx->pattern_locale);
                        $content = $content;
                        if (strpos($this->pattern, '$') !== false) {
                            $content = str_replace('$', CURRENCY[$this->pattern_currency_symbol], $content);
                        }
                    } catch (Exception $e) {
                        throw new ReportBroError(new StandardError('errorMsgInvalidPattern', $this->id, 'pattern', $this->content));
                    }
                } 
                else if (is_date($content)) {
                    try {
                        $content = format_datetime($content, $this->pattern, $ctx->pattern_locale);
                    } catch (Exception $e) {
                        throw new ReportBroError(new StandardError('errorMsgInvalidPattern', $this->id, 'pattern', $this->content));
                    }
                }
            }
            $content = strval($content);
        } else {
            $content = $ctx->fill_parameters($this->content, $this->id, 'content', $this->pattern);
        }

        if ($this->link) {
            $this->link = $ctx->fill_parameters($this->link, $this->id, 'link');
        }

        if ($this->cs_condition) {
            if ($ctx->evaluate_expression($this->cs_condition, $this->id, 'cs_condition')) {
                $this->used_style = $this->conditional_style;
            } else {
                $this->used_style = $this->style;
            }
        } else {
            $this->used_style = $this->style;
        }
        if ($this->used_style->vertical_alignment != VerticalAlignment::top() && !$this->always_print_on_same_page && !($this instanceof TableTextElement)) {
            $this->always_print_on_same_page = true;
        }
        $available_width = $this->width - $this->used_style->padding_left - $this->used_style->padding_right;

        $this->text_lines = array();
        if ($pdf_doc) {
            $pdf_doc->SetFont($this->used_style->font, $this->used_style->font_style, $this->used_style->font_size, $this->used_style->underline);
            if ($content) {
                try {
                    $lines = $pdf_doc->SplitLines($content, $available_width);
                } catch (Exception $e) {
                    throw new ReportBroError(new StandardError('errorMsgUnicodeEncodeError', $this->id, 'content', $this->content));
                }
            } else {
                $lines = array();
            }
            $this->line_height = $this->used_style->font_size * $this->used_style->line_spacing;
            $this->lines_count = count($lines);
            if ($this->lines_count > 0) {
                $this->text_height = (count($lines) - 1) * $this->line_height + $this->used_style->font_size;
            }
            $this->line_index = 0;
            foreach ($lines as $line) {
                array_push($this->text_lines, new TextLine($line, $available_width, $this->used_style, $this->link));
            }
            if ($this instanceof TableTextElement) {
                $this->total_height = max($this->text_height + $this->used_style->padding_top + $this->used_style->padding_bottom, $this->height);
            } else {
                $this->set_height($this->height);
            }
        } else {
            $this->content = $content;
            // set text_lines so is_printed can check for empty element when rendering spreadsheet
            if ($content) {
                $this->text_lines = array($content);
            }
        }
    }

    function set_height($height) {
        $this->height = $height;
        $this->space_top = 0;
        $this->space_bottom = 0;
        if ($this->text_height > 0) {
            $total_height = $this->text_height + $this->used_style->padding_top + $this->used_style->padding_bottom;
        } else {
            $total_height = 0;
        }
        if ($total_height < $height) {
            $remaining_space = $height - $total_height;
            if ($this->used_style->vertical_alignment == VerticalAlignment::top()) {
                $this->space_bottom = $remaining_space;
            } else if ($this->used_style->vertical_alignment == VerticalAlignment::middle()) {
                $this->space_top = $remaining_space / 2;
                $this->space_bottom = $remaining_space / 2;
            } else if ($this->used_style->vertical_alignment == VerticalAlignment::bottom()) {
                $this->space_top = $remaining_space;
            }
        }
        $this->total_height = $total_height + $this->space_top + $this->space_bottom;
    }

    function get_next_render_element($offset_y, $container_height, $ctx, $pdf_doc) {
        $available_height = $container_height - $offset_y;
        if ($this->always_print_on_same_page && $this->first_render_element && $this->total_height > $available_height && $offset_y != 0) {
            return array(null, false);
        }

        $lines = array();
        $remaining_height = $available_height;
        $block_height = 0;
        $text_height = 0;
        $text_offset_y = 0;
        if ($this->space_top > 0) {
            $space_top = min($this->space_top, $remaining_height);
            $this->space_top -= $space_top;
            $block_height += $space_top;
            $remaining_height -= $space_top;
            $text_offset_y = $space_top;
        }
        if ($this->space_top == 0) {
            $first_line = true;
            while ($this->line_index < $this->lines_count) {
                $last_line = ($this->line_index >= $this->lines_count - 1);
                $line_height = $first_line ? $this->used_style->font_size : $this->line_height;
                $tmp_height = $line_height;
                if ($this->line_index == 0) {
                    $tmp_height += $this->used_style->padding_top;
                }
                if ($last_line) {
                    $tmp_height += $this->used_style->padding_bottom;
                }
                if ($tmp_height > $remaining_height) {
                    break;
                }
                array_push($lines, $this->text_lines[$this->line_index]);
                $remaining_height -= $tmp_height;
                $block_height += $tmp_height;
                $text_height += $line_height;
                $this->line_index += 1;
                $first_line = false;
            }
        }

        if ($this->line_index >= $this->lines_count and $this->space_bottom > 0) {
            $space_bottom = min($this->space_bottom, $remaining_height);
            $this->space_bottom -= $space_bottom;
            $block_height += $space_bottom;
            $remaining_height -= $space_bottom;
        }

        if ($this->space_top == 0 and $this->line_index == 0 and $this->lines_count > 0) {
            // even first line does not fit
            if ($offset_y != 0) {
                // try on next container
                return array(null, false);
            } else {
                // already on top of container -> throw new error
                throw new Exception(new StandardError('errorMsgInvalidSize', $this->id, 'size'));
            }
        }
        
        $rendering_complete = ($this->line_index >= $this->lines_count && $this->space_top == 0 && $this->space_bottom == 0);
        if (!$rendering_complete and $remaining_height > 0) {
            // draw text block until end of container
            $block_height += $remaining_height;
            $remaining_height = 0;
        }

        if ($this->first_render_element and $rendering_complete) {
            $render_element_type = RenderElementType::complete();
        } else {
            if ($this->first_render_element) {
                $render_element_type = RenderElementType::first();
            } else if ($rendering_complete) {
                $render_element_type = RenderElementType::last();
                if ($this->used_style->vertical_alignment == VerticalAlignment::bottom()) {
                    // make sure text is exactly aligned to bottom
                    $tmp_offset_y = $block_height - $this->used_style->padding_bottom - $text_height;
                    if ($tmp_offset_y > 0) {
                        $text_offset_y = $tmp_offset_y;
                    }
                }
            } else {
                $render_element_type = RenderElementType::between();
            }
        }

        $text_block_elem = new TextBlockElement($this->report, $this->x, $this->y, $offset_y, $this->width, $block_height, $text_offset_y, $lines, $this->line_height, $render_element_type, $this->used_style);
        $this->first_render_element = false;
        $this->render_bottom = $text_block_elem->render_bottom;
        $this->rendering_complete = $rendering_complete;
        return array($text_block_elem, $rendering_complete);
    }

    function is_first_render_element() {
        return $this->first_render_element;
    }

    function render_spreadsheet($row, $col, $ctx, $renderer) {
        $cell_format = null;
        if (!$this->spreadsheet_cell_format_initialized) {
            $format_props = array();
            if ($this->used_style->bold) {
                $format_props['bold'] = true;
            }
            if ($this->used_style->italic) {
                $format_props['italic'] = true;
            }
            if ($this->used_style->underline) {
                $format_props['underline'] = true;
            }
            if ($this->used_style->strikethrough) {
                $format_props['font_strikeout'] = true;
            }
            if ($this->used_style->horizontal_alignment != HorizontalAlignment::left()) {
                $format_props['align'] = $this->used_style->horizontal_alignment->name;
            }
            if ($this->used_style->vertical_alignment != VerticalAlignment::top()) {
                if ($this->used_style->vertical_alignment == VerticalAlignment::middle()) {
                    $format_props['valign'] = 'vcenter';
                } else {
                    $format_props['valign'] = $this->used_style->vertical_alignment->name;
                }
            }
            if (!$this->used_style->text_color->is_black()) {
                $format_props['font_color'] = $this->used_style->text_color->color_code;
            }
            if (!$this->used_style->background_color->transparent) {
                $format_props['bg_color'] = $this->used_style->background_color->color_code;
            }
            if ($this->used_style->border_left || $this->used_style->border_top || $this->used_style->border_right || $this->used_style->border_bottom) {
                if (!$this->used_style->border_color->is_black()) {
                    $format_props['border_color'] = $this->used_style->border_color->color_code;
                }
                if ($this->used_style->border_left) {
                    $format_props['left'] = 1;
                }
                if ($this->used_style->border_top) {
                    $format_props['top'] = 1;
                }
                if ($this->used_style->border_right) {
                    $format_props['right'] = 1;
                }
                if ($this->used_style->border_bottom) {
                    $format_props['bottom'] = 1;
                }
            }
            if ($format_props) {
                $cell_format = $renderer->add_format($format_props);
                if ($this instanceof TableTextElement) {
                    // format can be used in following rows
                    $this->spreadsheet_cell_format = $cell_format;
                }
            }
            $this->spreadsheet_cell_format_initialized = true;
        } else {
            $cell_format = $this->spreadsheet_cell_format;
        }
        if ($this->spreadsheet_column) {
            $col = $this->spreadsheet_column - 1;
        }
        $renderer->write($row, $col, $this->spreadsheet_colspan, $this->content, $cell_format, $this->width);
        if ($this->spreadsheet_add_empty_row) {
            $row += 1;
        }
        return array($row + 1, $col + 1);
    }
}


class TextBlockElement extends DocElementBase {
    function __construct($report, $x, $y, $render_y, $width, $height, $text_offset_y, $lines, $line_height, $render_element_type, $style) {
        parent::__construct($report, (object)array('y'=>$y));
        $this->x = $x;
        $this->render_y = $render_y;
        $this->render_bottom = $render_y + $height;
        $this->width = $width;
        $this->height = $height;
        $this->text_offset_y = $text_offset_y;
        $this->lines = $lines;
        $this->line_height = $line_height;
        $this->render_element_type = $render_element_type;
        $this->style = $style;
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        $y = $container_offset_y + $this->render_y;
        if (!$this->style->background_color->transparent) {
            $pdf_doc->SetFillColor($this->style->background_color->r, $this->style->background_color->g, $this->style->background_color->b);
            $pdf_doc->Rect($this->x + $container_offset_x, $y, $this->width, $this->height, 'F');
        }
        if ($this->style->border_left || $this->style->border_top || $this->style->border_right || $this->style->border_bottom) {
            DocElement::draw_border($this->x+$container_offset_x, $y, $this->width, $this->height, $this->render_element_type, $this->style, $pdf_doc);
        }

        if (in_array($this->render_element_type, array(RenderElementType::complete(), RenderElementType::first()))) {
            $y += $this->style->padding_top;
        }
        $y += $this->text_offset_y;

        $underline = $this->style->underline;
        $last_line_index = count($this->lines) - 1;
        // underline for justified text is drawn manually to have a single line for the
        // whole text. each word is rendered individually,
        // therefor we can't use the underline style of the rendered text
        if ($this->style->horizontal_alignment == HorizontalAlignment::justify() && $last_line_index > 0) {
            $underline = false;
            $pdf_doc->SetDrawColor($this->style->text_color->r, $this->style->text_color->g, $this->style->text_color->b);
        }
        $pdf_doc->SetFont($this->style->font, $this->style->font_style, $this->style->font_size, $underline);
        $pdf_doc->SetTextColor($this->style->text_color->r, $this->style->text_color->g, $this->style->text_color->b);

        foreach ($this->lines as $i => $line) {
            $last_line = ($i == $last_line_index);
            $line->render_pdf($this->x + $container_offset_x + $this->style->padding_left, $y, $last_line, $pdf_doc);
            $y += $this->line_height;
        }
    }
}

class TextLine {
    function __construct($text, $width, $style, $link) {
        $this->text = $text;
        $this->width = $width;
        $this->style = $style;
        $this->link = $link;
    }

    function render_pdf($x, $y, $last_line, $pdf_doc) {
        $render_y = $y + $this->style->font_size * 0.8;
        $line_width = null;
        $offset_x = 0;
        if ($this->style->horizontal_alignment == HorizontalAlignment::justify()) {
            if ($last_line) {
                $pdf_doc->SetFont($this->style->font, $this->style->font_style, $this->style->font_size, $this->style->underline);
                $pdf_doc->Text($x, $render_y, $this->text);
            } else {
                $words = explode(' ', $this->text);
                $word_width = array();
                $total_word_width = 0;
                foreach ($words as $word) {
                    $tmp_width = $pdf_doc->GetStringWidth($word);
                    array_push($word_width, $tmp_width);
                    $total_word_width += $tmp_width;
                }
                $count_spaces = count($words) - 1;
                $word_spacing = $count_spaces > 0 ? (($this->width - $total_word_width) / $count_spaces) : 0;
                $word_x = $x;
                $pdf_doc->SetFont($this->style->font, $this->style->font_style, $this->style->font_size, false);
                foreach ($words as $i => $word) {
                    $pdf_doc->Text($word_x, $render_y, $word);
                    $word_x += $word_width[$i] + $word_spacing;
                }

                if ($this->style->underline) {
                    if (count($words) == 1) {
                        $text_width = $word_width[0];
                    } else {
                        $text_width = $this->width;
                    }
                    $underline_position = $pdf_doc->current_font['up'];
                    $underline_thickness = $pdf_doc->current_font['ut'];
                    $render_y += -$underline_position / 1000.0 * $this->style->font_size;
                    $underline_width = $underline_thickness / 1000.0 * $this->style->font_size;
                    $pdf_doc->SetLineWidth($underline_width);
                    $pdf_doc->Line($x, $render_y, $x + $text_width, $render_y);
                }

                if (count($words) > 1) {
                    $line_width = $this->width;
                } else if (count($words) > 0) {
                    $line_width = $word_width[0];
                }
            }
        } else {
            if ($this->style->horizontal_alignment != HorizontalAlignment::left()) {
                $line_width = $pdf_doc->GetStringWidth($this->text);
                $space = $this->width - $line_width;
                if ($this->style->horizontal_alignment == HorizontalAlignment::center()) {
                    $offset_x = ($space / 2);
                } else if ($this->style->horizontal_alignment == HorizontalAlignment::right()) {
                    $offset_x = $space;
                }
            }
            $pdf_doc->Text($x + $offset_x, $render_y, $this->text);
        }

        if ($this->style->strikethrough) {
            if ($line_width == null) {
                $line_width = $pdf_doc->GetStringWidth($this->text);
            }
            // use underline thickness
            $strikethrough_thickness = $pdf_doc->current_font['ut'];
            $render_y = $y + $this->style->font_size * 0.5;
            $strikethrough_width = $strikethrough_thickness / 1000.0 * $this->style->font_size;
            $pdf_doc->SetLineWidth($strikethrough_width);
            $pdf_doc->Line($x + $offset_x, $render_y, $x + $offset_x + $line_width, $render_y);
        }

        if ($this->link) {
            if ($line_width == null) {
                $line_width = $pdf_doc->GetStringWidth($this->text);
            }
            $pdf_doc->Link($x + $offset_x, $y, $line_width, $this->style->font_size, $this->link);
        }
    }
}


class TableTextElement extends TextElement {
    function __construct($report, $data) {
        parent::__construct($report, $data);
    }
}

class TableImageElement extends ImageElement {
    function __construct($report, $data) {
        parent::__construct($report, $data);
    }
}

class TableRow {
    function __construct($report, $table_band, $columns, $ctx, $prev_row = null) {
        if (count($columns) > count($table_band->column_data)) {
            throw new Exception('AssertionError');
        }
        $this->column_data = array();
        foreach ($columns as $column) {
            $column_element = new TableTextElement($report, $table_band->column_data[$column]);
            
            if ($column_element->content && !$column_element->eval && Context::is_parameter_name($column_element->content)) {
                $column_data_parameter = $ctx->get_parameter(Context::strip_parameter_name($column_element->content));
                if ($column_data_parameter && $column_data_parameter->type == ParameterType::image()) {
                    $column_element = new TableImageElement($report, $table_band->column_data[$column]);
                }
            }
            array_push($this->column_data, $column_element);

            if (property_exists($table_band->column_data[$column], 'simple_array')) {
                // in case value of column is a simple array parameter we create multiple columns,
                // one for each array entry of parameter data
                $is_simple_array = false;
                if ($column_element->content && !$column_element->eval && Context::is_parameter_name($column_element->content)) {
                    $column_data_parameter = $ctx->get_parameter(Context::strip_parameter_name($column_element->content));
                    if ($column_data_parameter && $column_data_parameter->type == ParameterType::simple_array()) {
                        $is_simple_array = true;
                        list($column_values, $parameter_exists) = $ctx->get_data($column_data_parameter->name);
                        foreach ($column_values as $idx => $column_value) {
                            $formatted_val = $ctx->get_formatted_value($column_value, $column_data_parameter, null, true);
                            if ($idx == 0) {
                                $column_element->content = $formatted_val;
                            } else {
                                $column_element = new TableTextElement($report, $table_band->column_data[$column]);
                                $column_element->content = $formatted_val;
                                array_push($this->column_data, $column_element);
                            }
                        }
                    }
                }
                // store info if column content is a simple array parameter to
                // avoid checks for the next rows
                $table_band->column_data[$column]['simple_array'] = $is_simple_array;
            }
        }

        $this->height = 0;
        $this->always_print_on_same_page = true;
        $this->table_band = $table_band;
        $this->render_elements = array();
        $this->background_color = $table_band->background_color;
        $this->alternate_background_color = $table_band->background_color;
        if ($table_band->band_type == BandType::content() && !$table_band->alternate_background_color->transparent) {
            $this->alternate_background_color = $table_band->alternate_background_color;
        }
        $this->group_expression = '';
        $this->print_if_result = true;
        $this->prev_row = $prev_row;
        $this->next_row = null;
        if ($prev_row != null) {
            $prev_row->next_row = $this;
        }
    }

    function is_printed($ctx) {
        $printed = $this->print_if_result;
        if ($printed && $this->table_band->group_expression) {
            if ($this->table_band->before_group) {
                $printed = ($this->prev_row == null || $this->group_expression != $this->prev_row->group_expression);
            } else {
                $printed = ($this->next_row == null || $this->group_expression != $this->next_row->group_expression);
            }
        }
        return $printed;
    }

    function prepare($ctx, $pdf_doc, $row_index = -1, $only_verify = false) {
        if ($only_verify) {
            foreach ($this->column_data as $column_element) {
                $column_element->prepare($ctx, $pdf_doc, true);
            }
        } else {
            if ($this->table_band->group_expression) {
                $this->group_expression = $ctx->evaluate_expression($this->table_band->group_expression, $this->table_band->id, 'group_expression');
            }
            if ($this->table_band->print_if) {
                $this->print_if_result = $ctx->evaluate_expression($this->table_band->print_if, $this->table_band->id, 'print_if');
            }
            $heights = array($this->table_band->height);
            foreach ($this->column_data as $column_element) {
                $column_element->prepare($ctx, $pdf_doc, false);
                array_push($heights, $column_element->total_height);
                if ($row_index != -1 && $row_index % 2 == 1) {
                    $background_color = $this->alternate_background_color;
                } else {
                    $background_color = $this->background_color;
                }
                if (!$background_color->transparent && $column_element->used_style->background_color->transparent) {
                    $column_element->used_style->background_color = $background_color;
                }
            }
            $this->height = max($heights);
            foreach ($this->column_data as $column_element) {
                $column_element->set_height($this->height);
            }
        }
    }

    function create_render_elements($offset_y, $container_height, $ctx, $pdf_doc) {
        foreach ($this->column_data as $column_element) {
            list($render_element) = $column_element->get_next_render_element($offset_y, $container_height, $ctx, $pdf_doc);
            if ($render_element == null) {
                throw new Exception('TableRow.create_render_elements failed - failed to create column render_element');
            }
            array_push($this->render_elements, $render_element);
        }
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        $x = $container_offset_x;
        foreach ($this->render_elements as $render_element) {
            $render_element->render_pdf($x, $container_offset_y, $pdf_doc);
            $x += $render_element->width;
        }
    }

    function render_spreadsheet($row, $col, $ctx, $renderer) {
        foreach ($this->column_data as $column_element) {
            $column_element->render_spreadsheet($row, $col, $ctx, $renderer);
            $col += 1;
        }
        return $row + 1;
    }

    function verify($ctx) {
        foreach ($this->column_data as $column_element) {
            $column_element->verify($ctx);
        }
    }

    function get_width() {
        $width = 0;
        foreach($this->column_data as $column_element) {
            $width += $column_element->width;
        }
        return $width;
    }

    function get_render_y() {
        if ($this->render_elements) {
            return $this->render_elements[0]->render_y;
        }
        return 0;
    }
}

class TableBlockElement extends DocElementBase {
    function __construct($report, $x, $width, $render_y, $table) {
        parent::__construct($report, (object)array("y"=>0));
        $this->report = $report;
        $this->x = $x;
        $this->width = $width;
        $this->render_y = $render_y;
        $this->render_bottom = $render_y;
        $this->table = $table;
        $this->rows = array();
        $this->complete = false;
    }

    function add_rows($rows, $allow_split, $available_height, $offset_y, $container_height, $ctx, $pdf_doc) {
        $rows_added = 0;
        if (!$this->complete) {
            if (!$allow_split) {
                $height = 0;
                foreach ($rows as $row) {
                    $height += $row->height;
                }
                if ($height <= $available_height) {
                    foreach ($rows as $row) {
                        $row->create_render_elements($offset_y, $container_height, $ctx, $pdf_doc);
                    }
                    $this->rows = array_merge($this->rows, $rows);
                    $rows_added = count($rows);
                    $available_height -= $height;
                    $this->height += $height;
                    $this->render_bottom += $height;
                } else {
                    $this->complete = true;
                }
            } else {
                foreach ($rows as $row) {
                    if ($row->height <= $available_height) {
                        $row->create_render_elements($offset_y, $container_height, $ctx, $pdf_doc);
                        array_push($this->rows, $row);
                        $rows_added += 1;
                        $available_height -= $row->height;
                        $this->height += $row->height;
                        $this->render_bottom += $row->height;
                    } else {
                        $this->complete = true;
                        break;
                    }
                }
            }
        }
        return $rows_added;
    }

    function is_empty() {
        return count($this->rows) == 0;
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        $y = $container_offset_y;
        foreach ($this->rows as $row) {
            $row->render_pdf($container_offset_x + $this->x, $y, $pdf_doc);
            $y += $row->height;
        }

        if ($this->rows and $this->table->border != Border::none()) {
            $pdf_doc->SetDrawColor($this->table->border_color->r, $this->table->border_color->g, $this->table->border_color->b);
            $pdf_doc->SetLineWidth($this->table->border_width);
            $half_border_width = $this->table->border_width / 2;
            $x1 = $container_offset_x + $this->x;
            $x2 = $x1 + $this->rows[0]->get_width();
            $x1 += $half_border_width;
            $x2 -= $half_border_width;
            $y1 = $this->rows[0]->get_render_y() + $container_offset_y;
            $y2 = $y1 + ($y - $container_offset_y);
            if (in_array($this->table->border, array(Border::grid(), Border::frame_row(), Border::frame()))) {
                $pdf_doc->Line($x1, $y1, $x1, $y2);
                $pdf_doc->Line($x2, $y1, $x2, $y2);
            }
            $y = $y1;
            $pdf_doc->Line($x1, $y1, $x2, $y1);
            if ($this->table->border != Border::frame()) {
                foreach (array_slice($this->rows, 0, 1) as $row) {
                    $y += $row->height;
                    $pdf_doc->Line($x1, $y, $x2, $y);
                }
            }
            $pdf_doc->Line($x1, $y2, $x2, $y2);
            if ($this->table->border == Border::grid()) {
                $columns = $this->rows[0]->column_data;
                // add half border_width so border is drawn inside right column and can be aligned with
                // borders of other elements outside the table
                $x = $x1;
                foreach(array_slice($columns, 0, 1) as $column) {
                    $x += $column->width;
                    $pdf_doc->Line($x, $y1, $x, $y2);
                }
            }
        }
    }
}

class TableElement extends DocElement {
    function __construct($report, $data) {
        parent::__construct($report, $data);
        $this->data_source = property_exists($data, 'dataSource') ? $data->{'dataSource'} : '';
        $this->columns = range(0, intval($data->{'columns'}) > 0 ? intval($data->{'columns'}) - 1 : 0);
        $header = boolval($data->{'header'});
        $footer = boolval($data->{'footer'});
        $this->header = $header ? new TableBandElement($data->{'headerData'}, BandType::header()) : null;
        $this->content_rows = array();
        $content_data_rows = $data->{'contentDataRows'};
        if (!is_array($content_data_rows)) {
            throw new Exception('AssertionError');
        }
        $main_content_created = false;
        foreach ($content_data_rows as $content_data_row) {
            $band_element = new TableBandElement($content_data_row, BandType::content(), !$main_content_created);
            if (!$main_content_created && !$band_element->group_expression) {
                $main_content_created = true;
            }
            array_push($this->content_rows, $band_element);
        }
        $this->footer = $footer ? new TableBandElement($data->{'footerData'}, BandType::footer()) : null;
        $this->print_header = ($this->header != null);
        $this->print_footer = ($this->footer != null);
        $this->border = Border::byName($data->{'border'});
        $this->border_color = new Color($data->{'borderColor'});
        $this->border_width = floatval($data->{'borderWidth'});
        $this->print_if = property_exists($data, 'printIf') ? $data->{'printIf'} : '';
        $this->remove_empty_element = boolval($data->{'removeEmptyElement'});
        $this->spreadsheet_hide = boolval($data->{'spreadsheet_hide'});
        $this->spreadsheet_column = intval($data->{'spreadsheet_column'});
        $this->spreadsheet_add_empty_row = boolval($data->{'spreadsheet_addEmptyRow'});
        $this->data_source_parameter = null;
        $this->row_parameters = array();
        $this->rows = array();
        $this->row_count = 0;
        $this->row_index = -1;
        $this->prepared_rows = array();  // type: List[TableRow]
        $this->prev_content_rows = array_fill(0, count($this->content_rows), array());  // type: List[TableRow]
        $this->width = 0;
        if ($this->header) {
            $this->height += $this->header->height;
        }
        if ($this->footer) {
            $this->height += $this->footer->height;
        }
        if (count($this->content_rows) > 0) {
            foreach ($this->content_rows as $content_row) {
                $this->height += $content_row->height;
            }
            foreach ($this->content_rows[0]->column_data as $column) {
                $this->width += property_exists($column, 'width') ? $column->{'width'} : 0;
            }
        }
        $this->bottom = $this->y + $this->height;
        $this->first_render_element = true;
    }

    function prepare($ctx, $pdf_doc, $only_verify) {
        if ($this->header) {
            foreach ($this->header->column_data as $column_idx => $column) {
                if ($column->{'printIf'}) {
                    $printed = $ctx->evaluate_expression($column->{'printIf'}, $column->{'id'}, 'print_if');
                    if (!$printed) {
                        unset($this->columns[$column_idx]);
                    }
                }
            }
        }
        $parameter_name = Context::strip_parameter_name($this->data_source);
        $this->data_source_parameter = null;
        if ($parameter_name) {
            $this->data_source_parameter = $ctx->get_parameter($parameter_name);
            if ($this->data_source_parameter == null) {
                throw new ReportBroError(new StandardError('errorMsgMissingParameter', $this->id, 'data_source'));
            }
            if ($this->data_source_parameter->type != ParameterType::_array()) {
                throw new ReportBroError(new StandardError('errorMsgInvalidDataSourceParameter', $this->id, 'data_source'));
            }
            foreach ($this->data_source_parameter->children as $row_parameter) {
                $this->row_parameters[$row_parameter->name] = $row_parameter;
            }
            list($this->rows, $parameter_exists) = $ctx->get_data($this->data_source_parameter->name);
            if (!$parameter_exists) {
                throw new ReportBroError(new StandardError('errorMsgMissingData', $this->id, 'data_source'));
            }
            if (!is_array($this->rows)) {
                throw new ReportBroError(new StandardError('errorMsgInvalidDataSource', $this->id, 'data_source'));
            }
        } else {
            // there is no data source parameter so we create a static table (faked by one empty data row)
            $this->rows = array();
        }

        $this->row_count = count($this->rows);
        $this->row_index = 0;

        if ($only_verify) {
            if ($this->print_header){ 
                $table_row = new TableRow($this->report, $this->header, $this->columns, $ctx);
                $table_row->prepare($ctx, null, true);
            }
            while ($this->row_index < $this->row_count) {
                // push data context of current row so values of current row can be accessed
                $ctx->push_context($this->row_parameters, $this->rows[$this->row_index]);
                foreach ($this->content_rows as $content_row) {
                    $table_row = new TableRow($this->report, $content_row, $this->columns, $ctx);
                    $table_row->prepare($ctx, null, $this->row_index, true);
                }
                    
                $ctx->pop_context();
                $this->row_index += 1;
            }
            if ($this->print_footer) {
                $table_row = new TableRow($this->report, $this->footer, $this->columns, $ctx);
                $table_row->prepare($ctx, null, true);
            }
        }
    }

    function get_next_render_element($offset_y, $container_height, $ctx, $pdf_doc) {
        $this->render_y = $offset_y;
        $this->render_bottom = $this->render_y;
        if ($this->is_rendering_complete()) {
            $this->rendering_complete = true;
            return array(null, true);
        }
        $render_element = new TableBlockElement($this->report, $this->x, $this->width, $offset_y, $this);

        // batch size can be anything >= 3 because each row needs previous and next row to evaluate
        // group expression (in case it is set), the batch size functionines the number of table rows
        // which will be prepared before they are rendered
        $batch_size = 10;
        $remaining_batch_size = $batch_size;

        // add header in case it is not already available in prepared rows (from previous page)
        if ($this->print_header && (count($this->prepared_rows) == 0 || $this->prepared_rows[0]->table_band->band_type != BandType::header())) {
            $table_row = new TableRow($this->report, $this->header, $this->columns, $ctx);
            $table_row->prepare($ctx, $pdf_doc);
            $this->prepared_rows[0] = $table_row;
            if (!$this->header->repeat_header) {
                $this->print_header = false;
            }
        }

        while ($this->row_index < $this->row_count) {
            // push data context of current row so values of current row can be accessed
            $ctx->push_context($this->row_parameters, $this->rows[$this->row_index]);

            foreach ($this->content_rows as $i => $content_row) {
                $table_row = new TableRow($this->report, $content_row, $this->columns, $ctx, $this->prev_content_rows[$i]);
                $table_row->prepare($ctx, $pdf_doc, $this->row_index);
                array_push($this->prepared_rows, $table_row);
                $this->prev_content_rows[$i] = $table_row;
            }
            $ctx->pop_context();
            $remaining_batch_size -= 1;
            $this->row_index += 1;
            if ($remaining_batch_size == 0) {
                $remaining_batch_size = $batch_size;
                if ($this->row_index < $this->row_count || !$this->print_footer) {
                    $this->update_render_element($render_element, $offset_y, $container_height, $ctx, $pdf_doc);
                    if ($render_element->complete) {
                        break;
                    }
                }
            }
        }

        if ($this->row_index >= $this->row_count && $this->print_footer) {
            $table_row = new TableRow($this->report, $this->footer, $this->columns, $ctx);
            $table_row->prepare($ctx, $pdf_doc);
            array_push($this->prepared_rows, $table_row);
            $this->print_footer = false;
        }

        $this->update_render_element($render_element, $offset_y, $container_height, $ctx, $pdf_doc);

        if ($this->is_rendering_complete()) {
            $this->rendering_complete = true;
        }

        if ($render_element->is_empty()) {
            return array(null, $this->rendering_complete);
        }

        $this->render_bottom = $render_element->render_bottom;
        $this->first_render_element = false;
        return array($render_element, $this->rendering_complete);
    }

    function update_render_element(&$render_element, $offset_y, $container_height, $ctx, $pdf_doc) {
        $available_height = $container_height - $offset_y;
        $filtered_rows = array();
        $rows_for_next_update = array();
        $all_rows_processed = ($this->row_index >= $this->row_count);
        foreach ($this->prepared_rows as $prepared_row) {
            if ($prepared_row->table_band->band_type == BandType::content()) {
                if ($prepared_row->next_row != null || $all_rows_processed) {
                    if ($prepared_row->is_printed($ctx)) {
                        array_push($filtered_rows, $prepared_row);
                    }
                } else {
                    array_push($rows_for_next_update, $prepared_row);
                }
            } else {
                array_push($filtered_rows, $prepared_row);
            }
        }
            
        while (!$render_element->complete && $filtered_rows) {
            $add_row_count = 1;
            if (count($filtered_rows) >= 2 && ($filtered_rows[0]->table_band->band_type == BandType::header() || end($filtered_rows)->table_band->band_type == BandType::footer())) {
                // make sure header row is not printed alone on a page
                $add_row_count = 2;
            }
            // allow splitting multiple rows (header + content or footer) in case we are already at top
            // of the container and there is not enough space for both rows
            $allow_split = ($offset_y == 0);
            $height = $available_height - $render_element->height;
            $rows_added = $render_element->add_rows(array_slice($filtered_rows, 0, $add_row_count), $allow_split, $height, $offset_y, $container_height, $ctx, $pdf_doc);
            if ($rows_added == 0) {
                break;
            }
            $filtered_rows = array_slice($filtered_rows, $rows_added);
            $this->first_render_element = false;
        }

        $this->prepared_rows = $filtered_rows;
        $this->prepared_rows = array_merge($this->prepared_rows, $rows_for_next_update);
    }

    function is_rendering_complete() {
        return ((!$this->print_header || ($this->header && $this->header->repeat_header)) && !$this->print_footer && $this->row_index >= $this->row_count && count($this->prepared_rows) == 0);
    }

    function render_spreadsheet($row, $col, $ctx, $renderer) {
        if ($this->spreadsheet_column) {
            $col = $this->spreadsheet_column - 1;
        }

        if ($this->print_header) {
            $table_row = new TableRow($this->report, $this->header, $this->columns, $ctx);
            $table_row->prepare($ctx, null);
            if ($table_row->is_printed($ctx)) {
                $row = $table_row->render_spreadsheet($row, $col, $ctx, $renderer);
            }
        }

        $data_context_added = false;
        while ($this->row_index < $this->row_count) {
            // push data context of current row so values of current row can be accessed
            if ($data_context_added) {
                $ctx->pop_context();
            } else {
                $data_context_added = true;
            }
            $ctx->push_context($this->row_parameters, $this->rows[$this->row_index]);

            foreach ($this->content_rows as $i => $content_row) {
                $table_row = new TableRow($this->report, $content_row, $this->columns, $ctx, $this->prev_content_rows[$i]);
                $table_row->prepare($ctx, null, $this->row_index);
                // render rows from previous preparation because we need next row set (used for group_expression)
                if ($this->prev_content_rows[$i] != null && $this->prev_content_rows[$i]->is_printed($ctx)) {
                    $row = $this->prev_content_rows[$i]->render_spreadsheet($row, $col, $ctx, $renderer);
                }

                $this->prev_content_rows[$i] = $table_row;
            }
            $this->row_index += 1;
        }
        if ($data_context_added) {
            $ctx->pop_context();
        }

        foreach ($this->prev_content_rows as $i => $prev_content_row) {
            if ($this->prev_content_rows[$i] != null && $this->prev_content_rows[$i]->is_printed($ctx)) {
                $row = $this->prev_content_rows[$i]->render_spreadsheet($row, $col, $ctx, $renderer);
            }
        }
        
        if ($this->print_footer) {
            $table_row = new TableRow($this->report, $this->footer, $this->columns, $ctx);
            $table_row->prepare($ctx, null);
            if ($table_row->is_printed($ctx)) {
                $row = $table_row->render_spreadsheet($row, $col, $ctx, $renderer);
            }
        }

        if ($this->spreadsheet_add_empty_row) {
            $row += 1;
        }
        return array($row, $col + $this->get_column_count());
    }

    function get_column_count() {
        return count($this->columns);
    }
}


class TableBandElement {
    function __construct($data, $band_type, $before_group = false) {
        $this->id = property_exists($data, 'id') ? $data->{'id'} : '';
        $this->height = intval($data->{'height'});
        $this->band_type = $band_type;
        if ($band_type == BandType::header()) {
            $this->repeat_header = boolval($data->{'repeatHeader'});
        } else {
            $this->repeat_header = null;
        }
        $this->background_color = new Color($data->{'backgroundColor'});
        if ($band_type == BandType::content()) {
            $this->alternate_background_color = new Color($data->{'alternateBackgroundColor'});
        } else {
            $this->alternate_background_color = null;
        }
        $this->column_data = $data->{'columnData'};
        $this->group_expression = property_exists($data, 'groupExpression') ? $data->{'groupExpression'} : '';
        $this->print_if = property_exists($data, 'printIf') ? $data->{'printIf'} : '';
        $this->before_group = $before_group;
        if (!is_array($this->column_data)) {
            throw new Exception("AssertionError");
        }
    }
}


class FrameBlockElement extends DocElementBase {
    function __construct($report, $frame, $render_y) {
        parent::__construct($report, (object)array("y"=>0));
        $this->report = $report;
        $this->x = $frame->x;
        $this->width = $frame->width;
        $this->border_style = $frame->border_style;
        $this->background_color = $frame->background_color;
        $this->render_y = $render_y;
        $this->render_bottom = $render_y;
        $this->height = 0;
        $this->elements = array();
        $this->render_element_type = RenderElementType::none();
        $this->complete = false;
    }

    function add_elements($container, $render_element_type, $height) {
        $this->elements = $container->render_elements;
        $this->render_element_type = $render_element_type;
        $this->render_bottom += $height;
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        $x = $this->x + $container_offset_x;
        $y = $this->render_y + $container_offset_y;
        $height = $this->render_bottom - $this->render_y;

        $content_x = $x;
        $content_width = $this->width;
        $content_y = $y;
        $content_height = $height;

        if ($this->border_style->border_left) {
            $content_x += $this->border_style->border_width;
            $content_width -= $this->border_style->border_width;
        }
        if ($this->border_style->border_right) {
            $content_width -= $this->border_style->border_width;
        }
        if ($this->border_style->border_top && in_array($this->render_element_type, array(RenderElementType::first(), RenderElementType::complete()))) {
            $content_y += $this->border_style->border_width;
            $content_height -= $this->border_style->border_width;
        }
        if ($this->border_style->border_bottom && in_array($this->render_element_type, array(RenderElementType::last(), RenderElementType::complete()))) {
            $content_height -= $this->border_style->border_width;
        }

        if (!$this->background_color->transparent) {
            $pdf_doc->SetFillColor($this->background_color->r, $this->background_color->g, $this->background_color->b);
            $pdf_doc->Rect($content_x, $content_y, $content_width, $content_height, 'F');
        }

        $render_y = $y;
        if ($this->border_style->border_top && in_array($this->render_element_type, array(RenderElementType::first(), RenderElementType::complete()))) {
            $render_y += $this->border_style->border_width;
        }
        foreach ($this->elements as $element) {
            $element->render_pdf($content_x, $content_y, $pdf_doc);
        }

        if ($this->border_style->border_left || $this->border_style->border_top || $this->border_style->border_right || $this->border_style->border_bottom) {
            DocElement::draw_border($x, $y, $this->width, $height, $this->render_element_type, $this->border_style, $pdf_doc);
        }
    }
}

class FrameElement extends DocElement {
    function __construct($report, $data, &$containers) {
        parent::__construct($report, $data);
        $this->background_color = new Color($data->{'backgroundColor'});
        $this->border_style = new BorderStyle($data);
        $this->print_if = property_exists($data, 'printIf') ? $data->{'printIf'} : '';
        $this->remove_empty_element = boolval($data->{'removeEmptyElement'});
        $this->shrink_to_content_height = boolval($data->{'shrinkToContentHeight'});
        $this->spreadsheet_hide = boolval($data->{'spreadsheet_hide'});
        $this->spreadsheet_column = intval($data->{'spreadsheet_column'});
        $this->spreadsheet_add_empty_row = boolval($data->{'spreadsheet_addEmptyRow'});

        // rendering_complete status for next page, in case rendering was not started on first page.
        $this->next_page_rendering_complete = false;
        // container content height of previous page, in case rendering was not started on first page
        $this->prev_page_content_height = 0;

        $this->render_element_type = RenderElementType::none();
        $this->container = new Frame($this->width, $this->height, strval($data->{'linkedContainerId'}), $containers, $report);
    }

    function get_used_height() {
        $height = $this->container->get_render_elements_bottom();
        if ($this->border_style->border_top && $this->render_element_type == RenderElementType::none()) {
            $height += $this->border_style->border_width;
        }
        if ($this->border_style->border_bottom) {
            $height += $this->border_style->border_width;
        }
        if ($this->render_element_type == RenderElementType::none() && !$this->shrink_to_content_height) {
            $height = max($this->height, $height);
        }
        return $height;
    }

    function prepare($ctx, $pdf_doc, $only_verify) {
        $this->container->prepare($ctx, $pdf_doc, $only_verify);
        $this->next_page_rendering_complete = false;
        $this->prev_page_content_height = 0;
        $this->render_element_type = RenderElementType::none();
    }

    function get_next_render_element($offset_y, $container_height, $ctx, $pdf_doc) {
        $this->render_y = $offset_y;
        $content_height = $container_height;
        $render_element = new FrameBlockElement($this->report, $this, $offset_y);

        if ($this->border_style->border_top && $this->render_element_type == RenderElementType::none()) {
            $content_height -= $this->border_style->border_width;
        }
        if ($this->border_style->border_bottom) {
            // this is not 100% correct because bottom border is only applied if frame fits
            // on current page. this should be negligible because the border is usually only a few pixels
            // and most of the time the frame fits on one page.
            // to get the exact height in advance would be quite hard and is probably not worth the effort ...
            $content_height -= $this->border_style->border_width;
        }

        if ($this->first_render_element) {
            $available_height = $container_height - $offset_y;
            $this->first_render_element = false;
            $rendering_complete = $this->container->create_render_elements($content_height, $ctx, $pdf_doc);

            $needed_height = $this->get_used_height();

            if ($rendering_complete && ($needed_height <= $available_height)) {
                // rendering is complete and all elements of frame fit on current page
                $this->rendering_complete = true;
                $this->render_bottom = $offset_y + $needed_height;
                $this->render_element_type = RenderElementType::complete();
                $render_element->add_elements($this->container, $this->render_element_type, $needed_height);
                return array($render_element, true);
            } else {
                if ($offset_y == 0) {
                    // rendering of frame elements does not fit on current page but
                    // we are already at the top of the page -> start rendering and continue on next page
                    $this->render_bottom = $offset_y + $available_height;
                    $this->render_element_type = RenderElementType::first();
                    $render_element->add_elements($this->container, $this->render_element_type, $available_height);
                    return array($render_element, false);
                } else {
                    // rendering of frame elements does not fit on current page -> start rendering on next page
                    $this->next_page_rendering_complete = $rendering_complete;
                    $this->prev_page_content_height = $content_height;
                    return array(null, false);
                }
            }
        }

        if ($this->render_element_type == RenderElementType::none()) {
            // render elements were already created on first call to get_next_render_element
            // but elements did not fit on first page

            if ($content_height == $this->prev_page_content_height) {
                // we don't have to create any render elements here because we can use
                // the previously created elements

                $this->rendering_complete = $this->next_page_rendering_complete;
            } else {
                // we cannot use previously created render elements because container height is different
                // on current page. this should be very unlikely but could happen when the frame should be
                // printed on the first page and header/footer are not shown on first page, i.e. the following
                // pages have a different content band size than the first page.

                $this->container->prepare($ctx, $pdf_doc);
                $this->rendering_complete = $this->container->create_render_elements($content_height, $ctx, $pdf_doc);
            }
        } else {
            $this->rendering_complete = $this->container->create_render_elements($content_height, $ctx, $pdf_doc);
        }
        $this->render_bottom = $offset_y + $this->get_used_height();

        if (!$this->rendering_complete) {
            // use whole size of container if frame is not rendered completely
            $this->render_bottom = $offset_y + $container_height;

            if ($this->render_element_type == RenderElementType::none()) {
                $this->render_element_type = RenderElementType::first();
            } else {
                $this->render_element_type = RenderElementType::between();
            }
        } else {
            if ($this->render_element_type == RenderElementType::none()) {
                $this->render_element_type = RenderElementType::complete();
            } else {
                $this->render_element_type = RenderElementType::last();
            }
        }
        $render_element->add_elements($this->container, $this->render_element_type, $this->get_used_height());
        return array($render_element, $this->rendering_complete);
    }

    function render_spreadsheet($row, $col, $ctx, $renderer) {
        if ($this->spreadsheet_column) {
            $col = $this->spreadsheet_column - 1;
        }
        list($row, $col) = $this->container->render_spreadsheet($row, $col, $ctx, $renderer);
        if ($this->spreadsheet_add_empty_row) {
            $row += 1;
        }
        return array($row, $col);
    }

    function cleanup() {
        $this->container->cleanup();
    }
}


class SectionBandElement {
    function __construct($report, $data, $band_type, &$containers) {
        if (!is_object($data)) {
            throw new Exception("AssertionError");
        }
        $this->id = property_exists($data, 'id') ? $data->{'id'} : '';
        $this->width = $report->document_properties->page_width - $report->document_properties->margin_left - $report->document_properties->margin_right;
        $this->height = intval($data->{'height'});
        $this->band_type = $band_type;
        if ($band_type == BandType::header()) { 
            $this->repeat_header = boolval($data->{'repeatHeader'});
            $this->always_print_on_same_page = true;
        } else {
            $this->repeat_header = null;
            $this->always_print_on_same_page = boolval($data->{'alwaysPrintOnSamePage'});
        }
        $this->shrink_to_content_height = boolval($data->{'shrinkToContentHeight'});

        $this->container = new Container(strval($data->{'linkedContainerId'}), $containers, $report);
        $this->container->width = $this->width;
        $this->container->height = $this->height;
        $this->container->allow_page_break = false;
        $this->rendering_complete = false;
        $this->prepare_container = true;
        $this->rendered_band_height = 0;
    }

    function prepare($ctx, $pdf_doc, $only_verify) {
        return;
    }

    function create_render_elements($offset_y, $container_height, $ctx, $pdf_doc) {
        $available_height = $container_height - $offset_y;
        if ($this->always_print_on_same_page && !$this->shrink_to_content_height && ($container_height - $offset_y) < $this->height) {
            // not enough space for whole band
            $this->rendering_complete = false;
        } else {
            if ($this->prepare_container) {
                $this->container->prepare($ctx, $pdf_doc);
                $this->rendered_band_height = 0;
            } else {
                $this->rendered_band_height += $this->container->used_band_height;
                // clear render elements from previous page
                $this->container->clear_rendered_elements();
            }
            $this->rendering_complete = $this->container->create_render_elements($available_height, $ctx, $pdf_doc);
        }

        if ($this->rendering_complete) {
            $remaining_min_height = $this->height - $this->rendered_band_height;
            if (!$this->shrink_to_content_height and $this->container->used_band_height < $remaining_min_height) {
                // rendering of band complete, make sure band is at least as large
                // as minimum height (even if it spans over more than 1 page)
                if ($remaining_min_height <= $available_height) {
                    $this->prepare_container = true;
                    $this->container->used_band_height = $remaining_min_height;
                } else {
                    // minimum height is larger than available space, continue on next page
                    $this->rendering_complete = false;
                    $this->prepare_container = false;
                    $this->container->used_band_height = $available_height;
                }
            } else {
                $this->prepare_container = true;
            }
        } else {
            if ($this->always_print_on_same_page) {
                // band must be printed on same page but available space is not enough,
                // try to render it on top of next page
                $this->prepare_container = true;
                if ($offset_y == 0) {
                    $field = $this->band_type == BandType::header() ? 'size' : 'always_print_on_same_page';
                    throw new ReportBroError(new StandardError('errorMsgSectionBandNotOnSamePage', $this->id, $field));
                }
            } else {
                $this->prepare_container = false;
                $this->container->first_element_offset_y = $available_height;
                $this->container->used_band_height = $available_height;
            }
        }
    }

    function get_used_band_height() {
        return $this->container->used_band_height;
    }

    function get_render_elements() {
        return $this->container->render_elements;
    }

}


class SectionBlockElement extends DocElementBase {
    function __construct($report, $render_y) {
        parent::__construct($report, (object)array("y"=>0));
        $this->report = $report;
        $this->render_y = $render_y;
        $this->render_bottom = $render_y;
        $this->height = 0;
        $this->bands = array();
        $this->complete = false;
    }

    function is_empty() {
        return count($this->bands) == 0;
    }

    function add_section_band($section_band) {
        if ($section_band->rendering_complete || !$section_band->always_print_on_same_page) {
            $band_height = $section_band->get_used_band_height();
            array_push($this->bands, array("height"=>$band_height, "elements"=>$section_band->get_render_elements()));
            $this->height += $band_height;
            $this->render_bottom += $band_height;
        }
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        $y = $this->render_y + $container_offset_y;
        foreach ($this->bands as $band) {
            foreach ($band['elements'] as $element) {
                $element->render_pdf($container_offset_x, $y, $pdf_doc);
            }
            $y += $band['height'];
        }
    }
}

class SectionElement extends DocElement {
    function __construct($report, $data, &$containers) {
        parent::__construct($report, $data);
        $this->data_source = property_exists($data, 'dataSource') ? $data->{'dataSource'} : '';
        $this->print_if = property_exists($data, 'printIf') ? $data->{'printIf'} : '';

        $header = boolval($data->{'header'});
        $footer = boolval($data->{'footer'});
        if ($header) {
            $this->header = new SectionBandElement($report, $data->{'headerData'}, BandType::header(), $containers);
        } else {
            $this->header = null;
        }
        $this->content = new SectionBandElement($report, $data->{'contentData'}, BandType::content(), $containers);
        if ($footer) {
            $this->footer = new SectionBandElement($report, $data->{'footerData'}, BandType::footer(), $containers);
        } else {
            $this->footer = null;
        }
        $this->print_header = ($this->header != null);

        $this->x = 0;
        $this->width = 0;
        $this->height = $this->content->height;
        if ($this->header) {
            $this->height += $this->header->height;
        }
        if ($this->footer) {
            $this->height += $this->footer->height;
        }
        $this->bottom = $this->y + $this->height;

        $this->data_source_parameter = null;
        $this->row_parameters = array();
        $this->rows = array();
        $this->row_count = 0;
        $this->row_index = -1;
    }

    function prepare($ctx, $pdf_doc, $only_verify) {
        $parameter_name = Context::strip_parameter_name($this->data_source);
        $this->data_source_parameter = $ctx->get_parameter($parameter_name);
        if (!$this->data_source_parameter) {
            throw new ReportBroError(new StandardError('errorMsgMissingDataSourceParameter', $this->id, 'data_source'));
        }
        if ($this->data_source_parameter->type != ParameterType::_array()) {
            throw new ReportBroError(new StandardError('errorMsgInvalidDataSourceParameter', $this->id, 'data_source'));
        }
        foreach ($this->data_source_parameter->children as $row_parameter) {
            $this->row_parameters[$row_parameter->name] = $row_parameter;
        }
        list($this->rows, $parameter_exists) = $ctx->get_data($this->data_source_parameter->name);
        if (!$parameter_exists) {
            throw new ReportBroError(new StandardError('errorMsgMissingData', $this->id, 'data_source'));
        }
        if (!is_array($this->rows)) {
            throw new ReportBroError(Error('errorMsgInvalidDataSource', $this->id, 'data_source'));
        }

        $this->row_count = count($this->rows);
        $this->row_index = 0;

        if ($only_verify) {
            if ($this->header) {
                $this->header->prepare($ctx, null, true);
            }
            while ($this->row_index < $this->row_count) {
                // push data context of current row so values of current row can be accessed
                $ctx->push_context($this->row_parameters, $this->rows[$this->row_index]);
                $this->content->prepare($ctx, null, true);
                $ctx->pop_context();
                $this->row_index += 1;
            }
            if ($this->footer) {
                $this->footer->prepare($ctx, null, true);
            }
        }
    }

    function get_next_render_element($offset_y, $container_height, $ctx, $pdf_doc) {
        $this->render_y = $offset_y;
        $this->render_bottom = $this->render_y;
        $render_element = new SectionBlockElement($this->report, $offset_y);

        if ($this->print_header) {
            $this->header->create_render_elements($offset_y, $container_height, $ctx, $pdf_doc);
            $render_element->add_section_band($this->header);
            if (!$this->header->rendering_complete) {
                return array($render_element, false);
            }
            if (!$this->header->repeat_header) {
                $this->print_header = false;
            }
        }
        while ($this->row_index < $this->row_count) {
            // push data context of current row so values of current row can be accessed
            $ctx->push_context($this->row_parameters, $this->rows[$this->row_index]);
            $this->content->create_render_elements($offset_y + $render_element->height, $container_height, $ctx, $pdf_doc);
            $ctx->pop_context();
            $render_element->add_section_band($this->content);
            if (!$this->content->rendering_complete) {
                return array($render_element, false);
            }
            $this->row_index += 1;
        }

        if ($this->footer) {
            $this->footer->create_render_elements($offset_y + $render_element->height, $container_height, $ctx, $pdf_doc);
            $render_element->add_section_band($this->footer);
            if (!$this->footer->rendering_complete) {
                return array($render_element, false);
            }
        }

        // all bands finished
        $this->rendering_complete = true;
        $this->render_bottom += $render_element->height;
        return array($render_element, true);
    }

    function render_spreadsheet($row, $col, $ctx, $renderer) {
        if ($this->header) {
            list($row) = $this->header->container->render_spreadsheet($row, $col, $ctx, $renderer);
        }
        list($row) = $this->content->container->render_spreadsheet($row, $col, $ctx, $renderer);
        if ($this->footer) {
            list($row) = $this->footer->container->render_spreadsheet($row, $col, $ctx, $renderer);
        }
        return array($row, $col);
    }

    function cleanup() {
        if ($this->header) {
            $this->header->container->cleanup();
        }
        $this->content->container->cleanup();
        if ($this->footer) {
            $this->footer->container->cleanup();
        }
    }
}
