<?php 
// from __future__ import unicode_literals
// from __future__ import division
// from babel.numbers import format_decimal
// from babel.dates import format_datetime
// from io import BytesIO, BufferedReader
// from typing import List
// import base64
// import datetime
// import decimal
// import os
// import re
// import tempfile
// import sys
// import uuid

// from .barcode128 import code128_image
// from .context import Context
// from .enums import *
// from .errors import Error, ReportBroError
// from .structs import Color, BorderStyle, TextStyle
// from .utils import get_float_value, get_int_value, to_string, PY2

// try:
//     from urllib.request import urlopen  # For Python 3.0 and later
// except ImportError:
//     from urllib2 import urlopen  # Fall back to Python 2's urllib2

// try:
//     basestring  # For Python 2, str and unicode
// except NameError:
//     basestring = str

class DocElementBase {
    function __construct($report, $data) {
        $this->report = $report;
        $this->id = null;
        $this->y = intval($data->{'y'});
        $this->render_y = 0;
        $this->render_bottom = 0;
        $this->bottom = $this->y;
        $this->height = 0;
        $this->print_if = null;
        $this->remove_empty_element = false;
        $this->spreadsheet_hide = true;
        $this->spreadsheet_column = null;
        $this->spreadsheet_add_empty_row = false;
        $this->first_render_element = true;
        $this->rendering_complete = false;
        $this->predecessors = array();
        $this->successors = array();
        $this->sort_order = 1;  # sort order for elements with same 'y'-value
    }

    function is_predecessor($elem) {
        # if bottom of element is above y-coord of first predecessor we do not need to store
        # the predecessor here because the element is already a predecessor of the first predecessor
        return $this->y >= $elem->bottom and (count($this->predecessors) == 0 || $elem->bottom > $this->predecessors[0]->y);
    }

    function add_predecessor($predecessor) {
        $this->predecessors = array_push($this->predecessors, $predecessor);
        $predecessor->successors = array_push($predecessor->successors, $this);
    }

    # returns True in case there is at least one predecessor which is not completely rendered yet
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
        return list(null, true);
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        return;
    }

    function render_spreadsheet($row, $col, $ctx, $renderer) {
        return list($row, $col);
    }

    function cleanup() {
        return;
    }
}

class DocElement extends DocElementBase {
    function __construct($report, $data) {
        parent::__construct($report, $data);
        $this->id = intval($data->{'id'});
        $this->x = intval($data->{'x'});
        $this->width = intval($data->{'width'});
        $this->height = intval($data->{'height'});
        $this->bottom = $this->y + $this->height;
    }

    function get_next_render_element($offset_y, $container_height, $ctx, $pdf_doc) {
        if ($offset_y + $this->height <= $container_height) {
            $this->render_y = $offset_y;
            $this->render_bottom = $offset_y + $this->height;
            $this->rendering_complete = true;
            return [$this, true];
        }
        return [null, false];
    }

    static function draw_border($x, $y, $width, $height, $render_element_type, $border_style, $pdf_doc) {
        $pdf_doc->set_draw_color($border_style->border_color->r, $border_style->border_color->g, $border_style->border_color->b);
        $pdf_doc->set_line_width($border_style->border_width);
        $border_offset = $border_style->border_width / 2;
        $border_x = $x + $border_offset;
        $border_y = $y + $border_offset;
        $border_width = $width - $border_style->border_width;
        $border_height = $height - $border_style->border_width;
        if ($border_style->border_all && $render_element_type == RenderElementType::complete()) {
            $pdf_doc->rect($border_x, $border_y, $border_width, $border_height, 'D');
        } else {
            if ($border_style->border_left) {
                $pdf_doc->line($border_x, $border_y, $border_x, $border_y + $border_height);
            }
            if ($border_style->border_top && in_array($render_element_type, array(RenderElementType::complete(), RenderElementType::first()))) {
                $pdf_doc->line($border_x, $border_y, $border_x + $border_width, $border_y);
            }
            if ($border_style->border_right) {
                $pdf_doc->line($border_x + $border_width, $border_y, $border_x + $border_width, $border_y + $border_height);
            }
            if ($border_style->border_bottom && in_array($render_element_type, array(RenderElementType::complete(), RenderElementType::last()))) {
                $pdf_doc->line($border_x, $border_y + $border_height, $border_x + $border_width, $border_y + $border_height);
            }
        }
    }
}

class ImageElement extends DocElement {
    function __construct($report, $data) {
        parent::__construct($report, $data);
        $this->eval = boolval($data->{'eval'});
        $this->source = $data->{'source'} ? $data->{'source'} : '';
        $this->content = $data->{'content'} ? $data->{'content'} : '';
        $this->isContent = false;
        if ($this->source == '') {
            $this->isContent = true;
        }
        $this->image = $data->{'image'} ? $data->{'image'} : '';
        $this->image_filename = $data->{'imageFilename'} ? $data->{'imageFilename'} : '';
        $this->horizontal_alignment = HorizontalAlignment::string($data->{'horizontalAlignment'});
        $this->vertical_alignment = VerticalAlignment::string($data->{'verticalAlignment'});
        $this->background_color = new Color($data->{'backgroundColor'});
        $this->print_if = $data->{'printIf'} ? $data->{'printIf'} : '';
        $this->remove_empty_element = boolval($data->{'removeEmptyElement'});
        $this->link = $data->{'link'} ? $data->{'link'} : '';
        $this->spreadsheet_hide = boolval($data->{'spreadsheet_hide'});
        $this->spreadsheet_column = intval($data->{'spreadsheet_column'});
        $this->spreadsheet_add_empty_row = boolval($data->{'spreadsheet_addEmptyRow'});
        $this->image_key = null;
        $this->image_type = null;
        $this->image_fp = null;
        $this->total_height = 0;
        $this->image_height = 0;
        $this->used_style = new TextStyle($data);
    }
    
    function set_height($height) {
        $this->height = $height;
        $this->space_top = 0;
        $this->space_bottom = 0;
        if ($this->image_height > 0) {
            $total_height = $this->image_height;
        } else {
            $total_height = 0;
        }
        if ($total_height < $height) {
            $remaining_space = $height - $total_height;
        }
        $this->total_height = $total_height;
    }

    function prepare($ctx, $pdf_doc, $only_verify) {
        if ($this->image_key) {
            return;
        }
        $img_data_b64 = null;
        $is_url = false;
        if ($this->source) {
            $source_parameter = $ctx->get_parameter(Context::strip_parameter_name($this->source));
            if ($source_parameter) {
                if ($source_parameter->type == ParameterType::string()) {
                    list($this->image_key, $parameter_exists) = $ctx->get_data($source_parameter->name);
                    $is_url = true;
                } else if ($source_parameter->type == ParameterType::image()) {
                    # image is available as base64 encoded or
                    # file object (only possible if report data is passed directly from python code
                    # and not via web request)
//                     img_data, parameter_exists = ctx.get_data(source_parameter.name)
//                     if isinstance(img_data, BufferedReader) or\
//                             (PY2 and isinstance(img_data, file)):
//                         $this->image_fp = img_data
//                         pos = img_data.name.rfind('.')
//                         $this->image_type = img_data.name[pos+1:] if pos != -1 else ''
//                     elif isinstance(img_data, basestring):
//                         img_data_b64 = img_data
                } else {
                    // raise ReportBroError(Error('errorMsgInvalidImageSourceParameter', object_id=$this->id, field='source'))
                }
            }
        } else {
//                 source = $this->source.strip()
//                 if source[0:2] == '${' and source[-1] == '}':
//                     raise ReportBroError(
//                         Error('errorMsgMissingParameter', object_id=$this->id, field='source'))
//                 $this->image_key = $this->source
//                 is_url = True
        }
//         if $this->isContent:
//             source_parameter = ctx.get_parameter(Context.strip_parameter_name($this->content))
//             if source_parameter:
//                 img_data, parameter_exists = ctx.get_data(source_parameter.name)
//                 if parameter_exists:
//                     img_data_b64 = img_data
            
//         if img_data_b64 is None and not is_url and $this->image_fp is None:
//             if $this->image_filename and $this->image:
//                 # static image base64 encoded within image element
//                 img_data_b64 = $this->image
//                 $this->image_key = $this->image_filename

//         if img_data_b64:
//             m = re.match('^data:image/(.+);base64,', img_data_b64)
//             if not m:
//                 raise ReportBroError(
//                     Error('errorMsgInvalidImage', object_id=$this->id, field='source'))
//             $this->image_type = m.group(1).lower()
//             img_data = base64.b64decode(re.sub('^data:image/.+;base64,', '', img_data_b64))
//             $this->image_fp = BytesIO(img_data)
//         elif is_url:
//             if not ($this->image_key and
//                     ($this->image_key.startswith("http://") or $this->image_key.startswith("https://"))):
//                 raise ReportBroError(
//                     Error('errorMsgInvalidImageSource', object_id=$this->id, field='source'))
//             pos = $this->image_key.rfind('.')
//             $this->image_type = $this->image_key[pos+1:] if pos != -1 else ''

//         if $this->image_type is not None:
//             if $this->image_type not in ('png', 'jpg', 'jpeg'):
//                 raise ReportBroError(
//                     Error('errorMsgUnsupportedImageType', object_id=$this->id, field='source'))
//             if not $this->image_key:
//                 # $this->image_key = 'image_' + str($this->id) + '.' + $this->image_type
//                 $this->image_key = uuid.uuid4().hex[:6].upper() + '.' + $this->image_type
//         $this->image = None

//         if $this->link:
//             $this->link = ctx.fill_parameters($this->link, $this->id, field='link')
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc) {
        $x = $this->x + $container_offset_x;
        $y = $this->render_y + $container_offset_y;
        if (!$this->background_color->transparent) {
            $pdf_doc->set_fill_color($this->background_color->r, $this->background_color->g, $this->background_color->b);
            $pdf_doc->rect($x, $y, $this->width, $this->height, 'F');
        }
        if ($this->image_key) {
            // $halign = array(HorizontalAlignment::left(): 'L', HorizontalAlignment.center: 'C', HorizontalAlignment.right: 'R'}.get($this->horizontal_alignment));
            // $valign = array(VerticalAlignment::top(): 'T', VerticalAlignment.middle: 'C', VerticalAlignment.bottom: 'B'}.get($this->vertical_alignment));
            // try:
            //     image_info = pdf_doc.image(
            //         $this->image_key, x, y, $this->width, $this->height, type=$this->image_type,
            //         image_fp=$this->image_fp, halign=halign, valign=valign)
            // except:
            //     raise ReportBroError(
            //         Error('errorMsgLoadingImageFailed', object_id=$this->id,
            //               field='source' if $this->source else 'image'))
        }

//             if $this->link:
//                 # horizontal and vertical alignment of image within given width and height
//                 # by keeping original image aspect ratio
//                 offset_x = offset_y = 0
//                 image_width, image_height = 100, 100 # image_info['w'], image_info['h']
//                 if image_width <= $this->width and image_height <= $this->height:
//                     image_display_width, image_display_height = image_width, image_height
//                 else:
//                     size_ratio = image_width / image_height
//                     tmp = $this->width / size_ratio
//                     if tmp <= $this->height:
//                         image_display_width = $this->width
//                         image_display_height = tmp
//                     else:
//                         image_display_width = $this->height * size_ratio
//                         image_display_height = $this->height
//                 if $this->horizontal_alignment == HorizontalAlignment.center:
//                     offset_x = ($this->width - image_display_width) / 2
//                 elif $this->horizontal_alignment == HorizontalAlignment.right:
//                     offset_x = $this->width - image_display_width
//                 if $this->vertical_alignment == VerticalAlignment.middle:
//                     offset_y = ($this->height - image_display_height) / 2
//                 elif $this->vertical_alignment == VerticalAlignment.bottom:
//                     offset_y = $this->height - image_display_height

    $pdf_doc->link($x + $offset_x, $y + $offset_y, $image_display_width, $image_display_height, $this->link);
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
        return list($row, $col);
    }

    function cleanup() {
        if ($this->image_key) {
            $this->image_key = null;
        }
    }
}

// class BarCodeElement(DocElement):
//     function __construct(self, report, data):
//         DocElement.__init__(self, report, data)
//         $this->content = data.get('content', '')
//         $this->format = data.get('format', '').lower()
//         assert $this->format == 'code128'
//         $this->display_value = bool(data.get('displayValue'))
//         $this->print_if = data.get('printIf', '')
//         $this->remove_empty_element = bool(data.get('removeEmptyElement'))
//         $this->spreadsheet_hide = bool(data.get('spreadsheet_hide'))
//         $this->spreadsheet_column = get_int_value(data, 'spreadsheet_column')
//         $this->spreadsheet_colspan = get_int_value(data, 'spreadsheet_colspan')
//         $this->spreadsheet_add_empty_row = bool(data.get('spreadsheet_addEmptyRow'))
//         $this->image_key = None
//         $this->image_height = $this->height - 22 if $this->display_value else $this->height

//     def is_printed(self, ctx):
//         if not $this->content:
//             return False
//         return DocElementBase.is_printed(self, ctx)

//     def prepare(self, ctx, pdf_doc, only_verify):
//         if $this->image_key:
//             return
//         $this->content = ctx.fill_parameters($this->content, $this->id, field='content')
//         if $this->content:
//             try:
//                 img = code128_image($this->content, height=$this->image_height, thickness=2, quiet_zone=False)
//             except:
//                 raise ReportBroError(
//                     Error('errorMsgInvalidBarCode', object_id=$this->id, field='content'))
//             if not only_verify:
//                 with tempfile.NamedTemporaryFile(delete=False, suffix='.png') as f:
//                     img.save(f.name)
//                     $this->image_key = f.name
//                     $this->width = img.width

//     def render_pdf(self, container_offset_x, container_offset_y, pdf_doc):
//         x = $this->x + container_offset_x
//         y = $this->render_y + container_offset_y
//         if $this->image_key:
//             pdf_doc.image($this->image_key, x, y, $this->width, $this->image_height)
//             if $this->display_value:
//                 pdf_doc.set_font('courier', 'B', 18)
//                 pdf_doc.set_text_color(0, 0, 0)
//                 content_width = pdf_doc.get_string_width($this->content)
//                 offset_x = ($this->width - content_width) / 2
//                 pdf_doc.text(x + offset_x, y + $this->image_height + 20, $this->content)

//     def render_spreadsheet(self, row, col, ctx, renderer):
//         if $this->content:
//             cell_format = dict()
//             if $this->spreadsheet_column:
//                 col = $this->spreadsheet_column - 1
//             renderer.write(row, col, $this->spreadsheet_colspan, $this->content, cell_format, $this->width)
//             row += 2 if $this->spreadsheet_add_empty_row else 1
//             col += 1
//         return row, col

//     def cleanup(self):
//         if $this->image_key:
//             os.unlink($this->image_key)
//             $this->image_key = None


// class LineElement(DocElement):
//     function __construct(self, report, data):
//         DocElement.__init__(self, report, data)
//         $this->color = Color(data.get('color'))
//         $this->print_if = data.get('printIf', '')

//     def render_pdf(self, container_offset_x, container_offset_y, pdf_doc):
//         pdf_doc.set_draw_color($this->color.r, $this->color.g, $this->color.b)
//         pdf_doc.set_line_width($this->height)
//         x = $this->x + container_offset_x
//         y = $this->render_y + container_offset_y + ($this->height / 2)
//         pdf_doc.line(x, y, x + $this->width, y)


class PageBreakElement extends DocElementBase {
    function __construct($report, $data) {
        parent::__construct($report, $data);
        $this->id = intval($data->{'id'});
        $this->x = 0;
        $this->width = 0;
        $this->sort_order = 0;  # sort order for elements with same 'y'-value, render page break before other elements
    }
}


// class TextElement(DocElement):
//     function __construct(self, report, data):
//         DocElement.__init__(self, report, data)
//         $this->content = data.get('content', '')
//         $this->eval = bool(data.get('eval'))
//         if data.get('styleId'):
//             $this->style = report.styles.get(get_int_value(data, 'styleId'))
//             if $this->style is None:
//                 raise RuntimeError('Style for text element {id} not found'.format(id=$this->id))
//         else:
//             $this->style = TextStyle(data)
//         $this->print_if = data.get('printIf', '')
//         $this->pattern = data.get('pattern', '')
//         $this->link = data.get('link', '')
//         $this->cs_condition = data.get('cs_condition')
//         if $this->cs_condition:
//             if data.get('cs_styleId'):
//                 $this->conditional_style = report.styles.get(int(data.get('cs_styleId')))
//                 if $this->conditional_style is None:
//                     raise RuntimeError('Conditional style for text element {id} not found'.format(id=$this->id))
//             else:
//                 $this->conditional_style = TextStyle(data, key_prefix='cs_')
//         else:
//             $this->conditional_style = None
//         if isinstance(self, TableTextElement):
//             $this->remove_empty_element = False
//             $this->always_print_on_same_page = False
//         else:
//             $this->remove_empty_element = bool(data.get('removeEmptyElement'))
//             $this->always_print_on_same_page = bool(data.get('alwaysPrintOnSamePage'))
//         $this->height = get_int_value(data, 'height')
//         $this->spreadsheet_hide = bool(data.get('spreadsheet_hide'))
//         $this->spreadsheet_column = get_int_value(data, 'spreadsheet_column')
//         $this->spreadsheet_colspan = get_int_value(data, 'spreadsheet_colspan')
//         $this->spreadsheet_add_empty_row = bool(data.get('spreadsheet_addEmptyRow'))
//         $this->text_height = 0
//         $this->line_index = -1
//         $this->line_height = 0
//         $this->lines_count = 0
//         $this->text_lines = None
//         $this->used_style = None
//         $this->space_top = 0
//         $this->space_bottom = 0
//         $this->total_height = 0
//         $this->spreadsheet_cell_format = None
//         $this->spreadsheet_cell_format_initialized = False

//     def is_printed(self, ctx):
//         if $this->remove_empty_element and len($this->text_lines) == 0:
//             return False
//         return DocElementBase.is_printed(self, ctx)

//     def prepare(self, ctx, pdf_doc, only_verify):
//         if $this->eval:
//             content = ctx.evaluate_expression($this->content, $this->id, field='content')
//             if $this->pattern:
//                 if isinstance(content, (int, float, decimal.Decimal)):
//                     try:
//                         content = format_decimal(content, $this->pattern, locale=ctx.pattern_locale)
//                         if $this->pattern.find('$') != -1:
//                             content = content.replace('$', ctx.pattern_currency_symbol)
//                     except ValueError:
//                         raise ReportBroError(
//                             Error('errorMsgInvalidPattern', object_id=$this->id, field='pattern', context=$this->content))
//                 elif isinstance(content, datetime.date):
//                     try:
//                         content = format_datetime(content, $this->pattern, locale=ctx.pattern_locale)
//                     except ValueError:
//                         raise ReportBroError(
//                             Error('errorMsgInvalidPattern', object_id=$this->id, field='pattern', context=$this->content))
//             content = to_string(content)
//         else:
//             content = ctx.fill_parameters($this->content, $this->id, field='content', pattern=$this->pattern)

//         if $this->link:
//             $this->link = ctx.fill_parameters($this->link, $this->id, field='link')

//         if $this->cs_condition:
//             if ctx.evaluate_expression($this->cs_condition, $this->id, field='cs_condition'):
//                 $this->used_style = $this->conditional_style
//             else:
//                 $this->used_style = $this->style
//         else:
//             $this->used_style = $this->style
//         if $this->used_style.vertical_alignment != VerticalAlignment.top and not $this->always_print_on_same_page and\
//                 not isinstance(self, TableTextElement):
//             $this->always_print_on_same_page = True
//         available_width = $this->width - $this->used_style.padding_left - $this->used_style.padding_right

//         $this->text_lines = []
//         if pdf_doc:
//             pdf_doc.set_font($this->used_style.font, $this->used_style.font_style, $this->used_style.font_size,
//                     underline=$this->used_style.underline)
//             if content:
//                 try:
//                     lines = pdf_doc.multi_cell(available_width, 0, content, align=$this->used_style.text_align, split_only=True)
//                 except UnicodeEncodeError:
//                     raise ReportBroError(
//                         Error('errorMsgUnicodeEncodeError', object_id=$this->id, field='content', context=$this->content))
//             else:
//                 lines = []
//             $this->line_height = $this->used_style.font_size * $this->used_style.line_spacing
//             $this->lines_count = len(lines)
//             if $this->lines_count > 0:
//                 $this->text_height = (len(lines) - 1) * $this->line_height + $this->used_style.font_size
//             $this->line_index = 0
//             for line in lines:
//                 $this->text_lines.append(TextLine(line, width=available_width, style=$this->used_style, link=$this->link))
//             if isinstance(self, TableTextElement):
//                 $this->total_height = max($this->text_height +\
//                         $this->used_style.padding_top + $this->used_style.padding_bottom, $this->height)
//             else:
//                 $this->set_height($this->height)
//         else:
//             $this->content = content
//             # set text_lines so is_printed can check for empty element when rendering spreadsheet
//             if content:
//                 $this->text_lines = [content]

//     def set_height(self, height):
//         $this->height = height
//         $this->space_top = 0
//         $this->space_bottom = 0
//         if $this->text_height > 0:
//             total_height = $this->text_height + $this->used_style.padding_top + $this->used_style.padding_bottom
//         else:
//             total_height = 0
//         if total_height < height:
//             remaining_space = height - total_height
//             if $this->used_style.vertical_alignment == VerticalAlignment.top:
//                 $this->space_bottom = remaining_space
//             elif $this->used_style.vertical_alignment == VerticalAlignment.middle:
//                 $this->space_top = remaining_space / 2
//                 $this->space_bottom = remaining_space / 2
//             elif $this->used_style.vertical_alignment == VerticalAlignment.bottom:
//                 $this->space_top = remaining_space
//         $this->total_height = total_height + $this->space_top + $this->space_bottom

//     def get_next_render_element(self, offset_y, container_height, ctx, pdf_doc):
//         available_height = container_height - offset_y
//         if $this->always_print_on_same_page and $this->first_render_element and\
//                 $this->total_height > available_height and offset_y != 0:
//             return None, False

//         lines = []
//         remaining_height = available_height
//         block_height = 0
//         text_height = 0
//         text_offset_y = 0
//         if $this->space_top > 0:
//             space_top = min($this->space_top, remaining_height)
//             $this->space_top -= space_top
//             block_height += space_top
//             remaining_height -= space_top
//             text_offset_y = space_top
//         if $this->space_top == 0:
//             first_line = True
//             while $this->line_index < $this->lines_count:
//                 last_line = ($this->line_index >= $this->lines_count - 1)
//                 line_height = $this->used_style.font_size if first_line else $this->line_height
//                 tmp_height = line_height
//                 if $this->line_index == 0:
//                     tmp_height += $this->used_style.padding_top
//                 if  last_line:
//                     tmp_height += $this->used_style.padding_bottom
//                 if tmp_height > remaining_height:
//                     break
//                 lines.append($this->text_lines[$this->line_index])
//                 remaining_height -= tmp_height
//                 block_height += tmp_height
//                 text_height += line_height
//                 $this->line_index += 1
//                 first_line = False

//         if $this->line_index >= $this->lines_count and $this->space_bottom > 0:
//             space_bottom = min($this->space_bottom, remaining_height)
//             $this->space_bottom -= space_bottom
//             block_height += space_bottom
//             remaining_height -= space_bottom

//         if $this->space_top == 0 and $this->line_index == 0 and $this->lines_count > 0:
//             # even first line does not fit
//             if offset_y != 0:
//                 # try on next container
//                 return None, False
//             else:
//                 # already on top of container -> raise error
//                 raise ReportBroError(
//                     Error('errorMsgInvalidSize', object_id=$this->id, field='size'))

//         rendering_complete = $this->line_index >= $this->lines_count and $this->space_top == 0 and $this->space_bottom == 0
//         if not rendering_complete and remaining_height > 0:
//             # draw text block until end of container
//             block_height += remaining_height
//             remaining_height = 0

//         if $this->first_render_element and rendering_complete:
//             render_element_type = RenderElementType.complete
//         else:
//             if $this->first_render_element:
//                 render_element_type = RenderElementType.first
//             elif rendering_complete:
//                 render_element_type = RenderElementType.last
//                 if $this->used_style.vertical_alignment == VerticalAlignment.bottom:
//                     # make sure text is exactly aligned to bottom
//                     tmp_offset_y = block_height - $this->used_style.padding_bottom - text_height
//                     if tmp_offset_y > 0:
//                         text_offset_y = tmp_offset_y
//             else:
//                 render_element_type = RenderElementType.between

//         text_block_elem = TextBlockElement($this->report, x=$this->x, y=$this->y, render_y=offset_y,
//                 width=$this->width, height=block_height, text_offset_y=text_offset_y,
//                 lines=lines, line_height=$this->line_height,
//                 render_element_type=render_element_type, style=$this->used_style)
//         $this->first_render_element = False
//         $this->render_bottom = text_block_elem.render_bottom
//         $this->rendering_complete = rendering_complete
//         return text_block_elem, rendering_complete

//     def is_first_render_element(self):
//         return $this->first_render_element

//     def render_spreadsheet(self, row, col, ctx, renderer):
//         cell_format = None
//         if not $this->spreadsheet_cell_format_initialized:
//             format_props = dict()
//             if $this->used_style.bold:
//                 format_props['bold'] = True
//             if $this->used_style.italic:
//                 format_props['italic'] = True
//             if $this->used_style.underline:
//                 format_props['underline'] = True
//             if $this->used_style.strikethrough:
//                 format_props['font_strikeout'] = True
//             if $this->used_style.horizontal_alignment != HorizontalAlignment.left:
//                 format_props['align'] = $this->used_style.horizontal_alignment.name
//             if $this->used_style.vertical_alignment != VerticalAlignment.top:
//                 if $this->used_style.vertical_alignment == VerticalAlignment.middle:
//                     format_props['valign'] = 'vcenter'
//                 else:
//                     format_props['valign'] = $this->used_style.vertical_alignment.name
//             if not $this->used_style.text_color.is_black():
//                 format_props['font_color'] = $this->used_style.text_color.color_code
//             if not $this->used_style.background_color.transparent:
//                 format_props['bg_color'] = $this->used_style.background_color.color_code
//             if $this->used_style.border_left or $this->used_style.border_top or\
//                     $this->used_style.border_right or $this->used_style.border_bottom:
//                 if not $this->used_style.border_color.is_black():
//                     format_props['border_color'] = $this->used_style.border_color.color_code
//                 if $this->used_style.border_left:
//                     format_props['left'] = 1
//                 if $this->used_style.border_top:
//                     format_props['top'] = 1
//                 if $this->used_style.border_right:
//                     format_props['right'] = 1
//                 if $this->used_style.border_bottom:
//                     format_props['bottom'] = 1
//             if format_props:
//                 cell_format = renderer.add_format(format_props)
//                 if isinstance(self, TableTextElement):
//                     # format can be used in following rows
//                     $this->spreadsheet_cell_format = cell_format
//             $this->spreadsheet_cell_format_initialized = True
//         else:
//             cell_format = $this->spreadsheet_cell_format
//         if $this->spreadsheet_column:
//             col = $this->spreadsheet_column - 1
//         renderer.write(row, col, $this->spreadsheet_colspan, $this->content, cell_format, $this->width)
//         if $this->spreadsheet_add_empty_row:
//             row += 1
//         return row + 1, col + 1


// class TextBlockElement(DocElementBase):
//     function __construct(self, report, x, y, render_y, width, height, text_offset_y,
//                 lines, line_height, render_element_type, style):
//         DocElementBase.__init__(self, report, dict(y=y))
//         $this->x = x
//         $this->render_y = render_y
//         $this->render_bottom = render_y + height
//         $this->width = width
//         $this->height = height
//         $this->text_offset_y = text_offset_y
//         $this->lines = lines
//         $this->line_height = line_height
//         $this->render_element_type = render_element_type
//         $this->style = style

//     def render_pdf(self, container_offset_x, container_offset_y, pdf_doc):
//         y = container_offset_y + $this->render_y
//         if not $this->style.background_color.transparent:
//             pdf_doc.set_fill_color($this->style.background_color.r, $this->style.background_color.g,
//                     $this->style.background_color.b)
//             pdf_doc.rect($this->x + container_offset_x, y, $this->width, $this->height, style='F')
//         if ($this->style.border_left or $this->style.border_top or
//                 $this->style.border_right or $this->style.border_bottom):
//             DocElement.draw_border(
//                 x=$this->x+container_offset_x, y=y, width=$this->width, height=$this->height,
//                 render_element_type=$this->render_element_type, border_style=$this->style, pdf_doc=pdf_doc)

//         if $this->render_element_type in (RenderElementType.complete, RenderElementType.first):
//             y += $this->style.padding_top
//         y += $this->text_offset_y

//         underline = $this->style.underline
//         last_line_index = len($this->lines) - 1
//         # underline for justified text is drawn manually to have a single line for the
//         # whole text. each word is rendered individually,
//         # therefor we can't use the underline style of the rendered text
//         if $this->style.horizontal_alignment == HorizontalAlignment.justify and last_line_index > 0:
//             underline = False
//             pdf_doc.set_draw_color($this->style.text_color.r, $this->style.text_color.g, $this->style.text_color.b)
//         pdf_doc.set_font($this->style.font, $this->style.font_style, $this->style.font_size, underline=underline)
//         pdf_doc.set_text_color($this->style.text_color.r, $this->style.text_color.g, $this->style.text_color.b)

//         for i, line in enumerate($this->lines):
//             last_line = (i == last_line_index)
//             line.render_pdf($this->x + container_offset_x + $this->style.padding_left, y,
//                             last_line=last_line, pdf_doc=pdf_doc)
//             y += $this->line_height


// class TextLine(object):
//     function __construct(self, text, width, style, link):
//         $this->text = text
//         $this->width = width
//         $this->style = style
//         $this->link = link

//     def render_pdf(self, x, y, last_line, pdf_doc):
//         render_y = y + $this->style.font_size * 0.8
//         line_width = None
//         offset_x = 0
//         if $this->style.horizontal_alignment == HorizontalAlignment.justify:
//             if last_line:
//                 pdf_doc.set_font(
//                     $this->style.font, $this->style.font_style, $this->style.font_size, underline=$this->style.underline)
//                 pdf_doc.text(x, render_y, $this->text)
//             else:
//                 words = $this->text.split()
//                 word_width = []
//                 total_word_width = 0
//                 for word in words:
//                     tmp_width = pdf_doc.get_string_width(word)
//                     word_width.append(tmp_width)
//                     total_word_width += tmp_width
//                 count_spaces = len(words) - 1
//                 word_spacing = (($this->width - total_word_width) / count_spaces) if count_spaces > 0 else 0
//                 word_x = x
//                 pdf_doc.set_font($this->style.font, $this->style.font_style, $this->style.font_size, underline=False)
//                 for i, word in enumerate(words):
//                     pdf_doc.text(word_x, render_y, word)
//                     word_x += word_width[i] + word_spacing

//                 if $this->style.underline:
//                     if len(words) == 1:
//                         text_width = word_width[0]
//                     else:
//                         text_width = $this->width
//                     underline_position = pdf_doc.current_font['up']
//                     underline_thickness = pdf_doc.current_font['ut']
//                     render_y += -underline_position / 1000.0 * $this->style.font_size
//                     underline_width = underline_thickness / 1000.0 * $this->style.font_size
//                     pdf_doc.set_line_width(underline_width)
//                     pdf_doc.line(x, render_y, x + text_width, render_y)

//                 if len(words) > 1:
//                     line_width = $this->width
//                 elif len(words) > 0:
//                     line_width = word_width[0]
//         else:
//             if $this->style.horizontal_alignment != HorizontalAlignment.left:
//                 line_width = pdf_doc.get_string_width($this->text)
//                 space = $this->width - line_width
//                 if $this->style.horizontal_alignment == HorizontalAlignment.center:
//                     offset_x = (space / 2)
//                 elif $this->style.horizontal_alignment == HorizontalAlignment.right:
//                     offset_x = space
//             pdf_doc.text(x + offset_x, render_y, $this->text)

//         if $this->style.strikethrough:
//             if line_width is None:
//                 line_width = pdf_doc.get_string_width($this->text)
//             # use underline thickness
//             strikethrough_thickness = pdf_doc.current_font['ut']
//             render_y = y + $this->style.font_size * 0.5
//             strikethrough_width = strikethrough_thickness / 1000.0 * $this->style.font_size
//             pdf_doc.set_line_width(strikethrough_width)
//             pdf_doc.line(x + offset_x, render_y, x + offset_x + line_width, render_y)

//         if $this->link:
//             if line_width is None:
//                 line_width = pdf_doc.get_string_width($this->text)
//             pdf_doc.link(x + offset_x, y, line_width, $this->style.font_size, $this->link)


// class TableTextElement(TextElement):
//     function __construct(self, report, data):
//         TextElement.__init__(self, report, data)

// class TableImageElement(ImageElement):
//     function __construct(self, report, data):
//         ImageElement.__init__(self, report, data)


// class TableRow(object):
//     function __construct(self, report, table_band, columns, ctx, prev_row=None):
//         assert len(columns) <= len(table_band.column_data)
//         $this->column_data = []
//         for column in columns:
//             column_element = TableTextElement(report, table_band.column_data[column])
            
//             if column_element.content and not column_element.eval and\
//                     Context.is_parameter_name(column_element.content):
//                 column_data_parameter = ctx.get_parameter(Context.strip_parameter_name(column_element.content))
//                 if column_data_parameter and column_data_parameter.type == ParameterType.image:
//                     column_element = TableImageElement(report, table_band.column_data[column])
            
//             $this->column_data.append(column_element)

//             if table_band.column_data[column].get('simple_array') != False:
//                 # in case value of column is a simple array parameter we create multiple columns,
//                 # one for each array entry of parameter data
//                 is_simple_array = False
//                 if column_element.content and not column_element.eval and\
//                         Context.is_parameter_name(column_element.content):
//                     column_data_parameter = ctx.get_parameter(Context.strip_parameter_name(column_element.content))
//                     if column_data_parameter and column_data_parameter.type == ParameterType.simple_array:
//                         is_simple_array = True
//                         column_values, parameter_exists = ctx.get_data(column_data_parameter.name)
//                         for idx, column_value in enumerate(column_values):
//                             formatted_val = ctx.get_formatted_value(column_value, column_data_parameter,
//                                                                     object_id=None, is_array_item=True)
//                             if idx == 0:
//                                 column_element.content = formatted_val
//                             else:
//                                 column_element = TableTextElement(report, table_band.column_data[column])
//                                 column_element.content = formatted_val
//                                 $this->column_data.append(column_element)
//                 # store info if column content is a simple array parameter to
//                 # avoid checks for the next rows
//                 table_band.column_data[column]['simple_array'] = is_simple_array

//         $this->height = 0
//         $this->always_print_on_same_page = True
//         $this->table_band = table_band
//         $this->render_elements = []
//         $this->background_color = table_band.background_color
//         $this->alternate_background_color = table_band.background_color
//         if table_band.band_type == BandType.content and not table_band.alternate_background_color.transparent:
//             $this->alternate_background_color = table_band.alternate_background_color
//         $this->group_expression = ''
//         $this->print_if_result = True
//         $this->prev_row = prev_row
//         $this->next_row = None
//         if prev_row is not None:
//             prev_row.next_row = self

//     def is_printed(self, ctx):
//         printed = $this->print_if_result
//         if printed and $this->table_band.group_expression:
//             if $this->table_band.before_group:
//                 printed = $this->prev_row is None or $this->group_expression != $this->prev_row.group_expression
//             else:
//                 printed = $this->next_row is None or $this->group_expression != $this->next_row.group_expression
//         return printed

//     def prepare(self, ctx, pdf_doc, row_index=-1, only_verify=False):
//         if only_verify:
//             for column_element in $this->column_data:
//                 column_element.prepare(ctx, pdf_doc, only_verify=True)
//         else:
//             if $this->table_band.group_expression:
//                 $this->group_expression = ctx.evaluate_expression(
//                     $this->table_band.group_expression, $this->table_band.id, field='group_expression')
//             if $this->table_band.print_if:
//                 $this->print_if_result = ctx.evaluate_expression(
//                     $this->table_band.print_if, $this->table_band.id, field='print_if')
//             heights = [$this->table_band.height]
//             for column_element in $this->column_data:
//                 column_element.prepare(ctx, pdf_doc, only_verify=False)
//                 heights.append(column_element.total_height)
//                 if row_index != -1 and row_index % 2 == 1:
//                     background_color = $this->alternate_background_color
//                 else:
//                     background_color = $this->background_color
//                 if not background_color.transparent and column_element.used_style.background_color.transparent:
//                     column_element.used_style.background_color = background_color
//             $this->height = max(heights)
//             for column_element in $this->column_data:
//                 column_element.set_height($this->height)

//     def create_render_elements(self, offset_y, container_height, ctx, pdf_doc):
//         for column_element in $this->column_data:
//             render_element, _ = column_element.get_next_render_element(
//                 offset_y=offset_y, container_height=container_height, ctx=ctx, pdf_doc=pdf_doc)
//             if render_element is None:
//                 raise RuntimeError('TableRow.create_render_elements failed - failed to create column render_element')
//             $this->render_elements.append(render_element)

//     def render_pdf(self, container_offset_x, container_offset_y, pdf_doc):
//         x = container_offset_x
//         for render_element in $this->render_elements:
//             render_element.render_pdf(container_offset_x=x, container_offset_y=container_offset_y, pdf_doc=pdf_doc)
//             x += render_element.width

//     def render_spreadsheet(self, row, col, ctx, renderer):
//         for column_element in $this->column_data:
//             column_element.render_spreadsheet(row, col, ctx, renderer)
//             col += 1
//         return row + 1

//     def verify(self, ctx):
//         for column_element in $this->column_data:
//             column_element.verify(ctx)

//     def get_width(self):
//         width = 0
//         for column_element in $this->column_data:
//             width += column_element.width
//         return width

//     def get_render_y(self):
//         if $this->render_elements:
//             return $this->render_elements[0].render_y
//         return 0


// class TableBlockElement(DocElementBase):
//     function __construct(self, report, x, width, render_y, table):
//         DocElementBase.__init__(self, report, dict(y=0))
//         $this->report = report
//         $this->x = x
//         $this->width = width
//         $this->render_y = render_y
//         $this->render_bottom = render_y
//         $this->table = table
//         $this->rows = []
//         $this->complete = False

//     def add_rows(self, rows, allow_split, available_height, offset_y, container_height, ctx, pdf_doc):
//         rows_added = 0
//         if not $this->complete:
//             if not allow_split:
//                 height = 0
//                 for row in rows:
//                     height += row.height
//                 if height <= available_height:
//                     for row in rows:
//                         row.create_render_elements(offset_y=offset_y, container_height=container_height,
//                                 ctx=ctx, pdf_doc=pdf_doc)
//                     $this->rows.extend(rows)
//                     rows_added = len(rows)
//                     available_height -= height
//                     $this->height += height
//                     $this->render_bottom += height
//                 else:
//                     $this->complete = True
//             else:
//                 for row in rows:
//                     if row.height <= available_height:
//                         row.create_render_elements(offset_y=offset_y, container_height=container_height,
//                                 ctx=ctx, pdf_doc=pdf_doc)
//                         $this->rows.append(row)
//                         rows_added += 1
//                         available_height -= row.height
//                         $this->height += row.height
//                         $this->render_bottom += row.height
//                     else:
//                         $this->complete = True
//                         break
//         return rows_added

//     def is_empty(self):
//         return len($this->rows) == 0

//     def render_pdf(self, container_offset_x, container_offset_y, pdf_doc):
//         y = container_offset_y
//         for row in $this->rows:
//             row.render_pdf(container_offset_x=container_offset_x + $this->x, container_offset_y=y, pdf_doc=pdf_doc)
//             y += row.height

//         if $this->rows and $this->table.border != Border.none:
//             pdf_doc.set_draw_color($this->table.border_color.r, $this->table.border_color.g, $this->table.border_color.b)
//             pdf_doc.set_line_width($this->table.border_width)
//             half_border_width = $this->table.border_width / 2
//             x1 = container_offset_x + $this->x
//             x2 = x1 + $this->rows[0].get_width()
//             x1 += half_border_width
//             x2 -= half_border_width
//             y1 = $this->rows[0].get_render_y() + container_offset_y
//             y2 = y1 + (y - container_offset_y)
//             if $this->table.border in (Border.grid, Border.frame_row, Border.frame):
//                 pdf_doc.line(x1, y1, x1, y2)
//                 pdf_doc.line(x2, y1, x2, y2)
//             y = y1
//             pdf_doc.line(x1, y1, x2, y1)
//             if $this->table.border != Border.frame:
//                 for row in $this->rows[:-1]:
//                     y += row.height
//                     pdf_doc.line(x1, y, x2, y)
//             pdf_doc.line(x1, y2, x2, y2)
//             if $this->table.border == Border.grid:
//                 columns = $this->rows[0].column_data
//                 # add half border_width so border is drawn inside right column and can be aligned with
//                 # borders of other elements outside the table
//                 x = x1
//                 for column in columns[:-1]:
//                     x += column.width
//                     pdf_doc.line(x, y1, x, y2)


// class TableElement(DocElement):
//     function __construct(self, report, data):
//         DocElement.__init__(self, report, data)
//         $this->data_source = data.get('dataSource', '')
//         $this->columns = list(range(get_int_value(data, 'columns')))
//         header = bool(data.get('header'))
//         footer = bool(data.get('footer'))
//         $this->header = TableBandElement(data.get('headerData'), BandType.header) if header else None
//         $this->content_rows = []
//         content_data_rows = data.get('contentDataRows')
//         assert isinstance(content_data_rows, list)
//         main_content_created = False
//         for content_data_row in content_data_rows:
//             band_element = TableBandElement(content_data_row, BandType.content,
//                                             before_group=not main_content_created)
//             if not main_content_created and not band_element.group_expression:
//                 main_content_created = True
//             $this->content_rows.append(band_element)
//         $this->footer = TableBandElement(data.get('footerData'), BandType.footer) if footer else None
//         $this->print_header = $this->header is not None
//         $this->print_footer = $this->footer is not None
//         $this->border = Border[data.get('border')]
//         $this->border_color = Color(data.get('borderColor'))
//         $this->border_width = get_float_value(data, 'borderWidth')
//         $this->print_if = data.get('printIf', '')
//         $this->remove_empty_element = bool(data.get('removeEmptyElement'))
//         $this->spreadsheet_hide = bool(data.get('spreadsheet_hide'))
//         $this->spreadsheet_column = get_int_value(data, 'spreadsheet_column')
//         $this->spreadsheet_add_empty_row = bool(data.get('spreadsheet_addEmptyRow'))
//         $this->data_source_parameter = None
//         $this->row_parameters = dict()
//         $this->rows = []
//         $this->row_count = 0
//         $this->row_index = -1
//         $this->prepared_rows = []  # type: List[TableRow]
//         $this->prev_content_rows = [None] * len($this->content_rows)  # type: List[TableRow]
//         $this->width = 0
//         if $this->header:
//             $this->height += $this->header.height
//         if $this->footer:
//             $this->height += $this->footer.height
//         if len($this->content_rows) > 0:
//             for content_row in $this->content_rows:
//                 $this->height += content_row.height
//             for column in $this->content_rows[0].column_data:
//                 $this->width += column.get('width', 0)
//         $this->bottom = $this->y + $this->height
//         $this->first_render_element = True

//     def prepare(self, ctx, pdf_doc, only_verify):
//         if $this->header:
//             for column_idx, column in enumerate($this->header.column_data):
//                 if column.get('printIf'):
//                     printed = ctx.evaluate_expression(column.get('printIf'), column.get('id'), field='print_if')
//                     if not printed:
//                         del $this->columns[column_idx]
//         parameter_name = Context.strip_parameter_name($this->data_source)
//         $this->data_source_parameter = None
//         if parameter_name:
//             $this->data_source_parameter = ctx.get_parameter(parameter_name)
//             if $this->data_source_parameter is None:
//                 raise ReportBroError(
//                     Error('errorMsgMissingParameter', object_id=$this->id, field='data_source'))
//             if $this->data_source_parameter.type != ParameterType.array:
//                 raise ReportBroError(
//                     Error('errorMsgInvalidDataSourceParameter', object_id=$this->id, field='data_source'))
//             for row_parameter in $this->data_source_parameter.children:
//                 $this->row_parameters[row_parameter.name] = row_parameter
//             $this->rows, parameter_exists = ctx.get_data($this->data_source_parameter.name)
//             if not parameter_exists:
//                 raise ReportBroError(
//                     Error('errorMsgMissingData', object_id=$this->id, field='data_source'))
//             if not isinstance($this->rows, list):
//                 raise ReportBroError(
//                     Error('errorMsgInvalidDataSource', object_id=$this->id, field='data_source'))
//         else:
//             # there is no data source parameter so we create a static table (faked by one empty data row)
//             $this->rows = [dict()]

//         $this->row_count = len($this->rows)
//         $this->row_index = 0

//         if only_verify:
//             if $this->print_header:
//                 table_row = TableRow($this->report, $this->header, $this->columns, ctx=ctx)
//                 table_row.prepare(ctx, pdf_doc=None, only_verify=True)
//             while $this->row_index < $this->row_count:
//                 # push data context of current row so values of current row can be accessed
//                 ctx.push_context($this->row_parameters, $this->rows[$this->row_index])
//                 for content_row in $this->content_rows:
//                     table_row = TableRow($this->report, content_row, $this->columns, ctx=ctx)
//                     table_row.prepare(ctx, pdf_doc=None, row_index=$this->row_index, only_verify=True)
//                 ctx.pop_context()
//                 $this->row_index += 1
//             if $this->print_footer:
//                 table_row = TableRow($this->report, $this->footer, $this->columns, ctx=ctx)
//                 table_row.prepare(ctx, pdf_doc=None, only_verify=True)

//     def get_next_render_element(self, offset_y, container_height, ctx, pdf_doc):
//         $this->render_y = offset_y
//         $this->render_bottom = $this->render_y
//         if $this->is_rendering_complete():
//             $this->rendering_complete = True
//             return None, True
//         render_element = TableBlockElement($this->report, $this->x, $this->width, offset_y, self)

//         # batch size can be anything >= 3 because each row needs previous and next row to evaluate
//         # group expression (in case it is set), the batch size defines the number of table rows
//         # which will be prepared before they are rendered
//         batch_size = 10
//         remaining_batch_size = batch_size

//         # add header in case it is not already available in prepared rows (from previous page)
//         if $this->print_header and (len($this->prepared_rows) == 0 or
//                 $this->prepared_rows[0].table_band.band_type != BandType.header):
//             table_row = TableRow($this->report, $this->header, $this->columns, ctx=ctx)
//             table_row.prepare(ctx, pdf_doc)
//             $this->prepared_rows.insert(0, table_row)
//             if not $this->header.repeat_header:
//                 $this->print_header = False

//         while $this->row_index < $this->row_count:
//             # push data context of current row so values of current row can be accessed
//             ctx.push_context($this->row_parameters, $this->rows[$this->row_index])

//             for i, content_row in enumerate($this->content_rows):
//                 table_row = TableRow($this->report, content_row, $this->columns,
//                                      ctx=ctx, prev_row=$this->prev_content_rows[i])
//                 table_row.prepare(ctx, pdf_doc, row_index=$this->row_index)
//                 $this->prepared_rows.append(table_row)
//                 $this->prev_content_rows[i] = table_row
//             ctx.pop_context()
//             remaining_batch_size -= 1
//             $this->row_index += 1
//             if remaining_batch_size == 0:
//                 remaining_batch_size = batch_size
//                 if $this->row_index < $this->row_count or not $this->print_footer:
//                     $this->update_render_element(render_element, offset_y, container_height, ctx, pdf_doc)
//                     if render_element.complete:
//                         break

//         if $this->row_index >= $this->row_count and $this->print_footer:
//             table_row = TableRow($this->report, $this->footer, $this->columns, ctx=ctx)
//             table_row.prepare(ctx, pdf_doc)
//             $this->prepared_rows.append(table_row)
//             $this->print_footer = False

//         $this->update_render_element(render_element, offset_y, container_height, ctx, pdf_doc)

//         if $this->is_rendering_complete():
//             $this->rendering_complete = True

//         if render_element.is_empty():
//             return None, $this->rendering_complete

//         $this->render_bottom = render_element.render_bottom
//         $this->first_render_element = False
//         return render_element, $this->rendering_complete

//     def update_render_element(self, render_element, offset_y, container_height, ctx, pdf_doc):
//         available_height = container_height - offset_y
//         filtered_rows = []
//         rows_for_next_update = []
//         all_rows_processed = ($this->row_index >= $this->row_count)
//         for prepared_row in $this->prepared_rows:
//             if prepared_row.table_band.band_type == BandType.content:
//                 if prepared_row.next_row is not None or all_rows_processed:
//                     if prepared_row.is_printed(ctx):
//                         filtered_rows.append(prepared_row)
//                 else:
//                     rows_for_next_update.append(prepared_row)
//             else:
//                 filtered_rows.append(prepared_row)

//         while not render_element.complete and filtered_rows:
//             add_row_count = 1
//             if len(filtered_rows) >= 2 and\
//                     (filtered_rows[0].table_band.band_type == BandType.header or
//                      filtered_rows[-1].table_band.band_type == BandType.footer):
//                 # make sure header row is not printed alone on a page
//                 add_row_count = 2
//             # allow splitting multiple rows (header + content or footer) in case we are already at top
//             # of the container and there is not enough space for both rows
//             allow_split = (offset_y == 0)
//             height = available_height - render_element.height
//             rows_added = render_element.add_rows(
//                 filtered_rows[:add_row_count], allow_split=allow_split,
//                 available_height=height, offset_y=offset_y, container_height=container_height,
//                 ctx=ctx, pdf_doc=pdf_doc)
//             if rows_added == 0:
//                 break
//             filtered_rows = filtered_rows[rows_added:]
//             $this->first_render_element = False

//         $this->prepared_rows = filtered_rows
//         $this->prepared_rows.extend(rows_for_next_update)

//     def is_rendering_complete(self):
//         return (not $this->print_header or ($this->header and $this->header.repeat_header)) and\
//                not $this->print_footer and $this->row_index >= $this->row_count and len($this->prepared_rows) == 0

//     def render_spreadsheet(self, row, col, ctx, renderer):
//         if $this->spreadsheet_column:
//             col = $this->spreadsheet_column - 1

//         if $this->print_header:
//             table_row = TableRow($this->report, $this->header, $this->columns, ctx=ctx)
//             table_row.prepare(ctx, pdf_doc=None)
//             if table_row.is_printed(ctx):
//                 row = table_row.render_spreadsheet(row, col, ctx, renderer)

//         data_context_added = False
//         while $this->row_index < $this->row_count:
//             # push data context of current row so values of current row can be accessed
//             if data_context_added:
//                 ctx.pop_context()
//             else:
//                 data_context_added = True
//             ctx.push_context($this->row_parameters, $this->rows[$this->row_index])

//             for i, content_row in enumerate($this->content_rows):
//                 table_row = TableRow(
//                     $this->report, content_row, $this->columns, ctx=ctx, prev_row=$this->prev_content_rows[i])
//                 table_row.prepare(ctx, pdf_doc=None, row_index=$this->row_index)
//                 # render rows from previous preparation because we need next row set (used for group_expression)
//                 if $this->prev_content_rows[i] is not None and $this->prev_content_rows[i].is_printed(ctx):
//                     row = $this->prev_content_rows[i].render_spreadsheet(row, col, ctx, renderer)

//                 $this->prev_content_rows[i] = table_row
//             $this->row_index += 1
//         if data_context_added:
//             ctx.pop_context()

//         for i, prev_content_row in enumerate($this->prev_content_rows):
//             if $this->prev_content_rows[i] is not None and $this->prev_content_rows[i].is_printed(ctx):
//                 row = $this->prev_content_rows[i].render_spreadsheet(row, col, ctx, renderer)

//         if $this->print_footer:
//             table_row = TableRow($this->report, $this->footer, $this->columns, ctx=ctx)
//             table_row.prepare(ctx, pdf_doc=None)
//             if table_row.is_printed(ctx):
//                 row = table_row.render_spreadsheet(row, col, ctx, renderer)

//         if $this->spreadsheet_add_empty_row:
//             row += 1
//         return row, col + $this->get_column_count()

//     def get_column_count(self):
//         return len($this->columns)


// class TableBandElement(object):
//     function __construct(self, data, band_type, before_group=False):
//         $this->id = data.get('id', '')
//         $this->height = get_int_value(data, 'height')
//         $this->band_type = band_type
//         if band_type == BandType.header:
//             $this->repeat_header = bool(data.get('repeatHeader'))
//         else:
//             $this->repeat_header = None
//         $this->background_color = Color(data.get('backgroundColor'))
//         if band_type == BandType.content:
//             $this->alternate_background_color = Color(data.get('alternateBackgroundColor'))
//         else:
//             $this->alternate_background_color = None
//         $this->column_data = data.get('columnData')
//         $this->group_expression = data.get('groupExpression', '')
//         $this->print_if = data.get('printIf', '')
//         $this->before_group = before_group
//         assert isinstance($this->column_data, list)


// class FrameBlockElement(DocElementBase):
//     function __construct(self, report, frame, render_y):
//         DocElementBase.__init__(self, report, dict(y=0))
//         $this->report = report
//         $this->x = frame.x
//         $this->width = frame.width
//         $this->border_style = frame.border_style
//         $this->background_color = frame.background_color
//         $this->render_y = render_y
//         $this->render_bottom = render_y
//         $this->height = 0
//         $this->elements = []
//         $this->render_element_type = RenderElementType.none
//         $this->complete = False

//     def add_elements(self, container, render_element_type, height):
//         $this->elements = list(container.render_elements)
//         $this->render_element_type = render_element_type
//         $this->render_bottom += height

//     def render_pdf(self, container_offset_x, container_offset_y, pdf_doc):
//         x = $this->x + container_offset_x
//         y = $this->render_y + container_offset_y
//         height = $this->render_bottom - $this->render_y

//         content_x = x
//         content_width = $this->width
//         content_y = y
//         content_height = height

//         if $this->border_style.border_left:
//             content_x += $this->border_style.border_width
//             content_width -= $this->border_style.border_width
//         if $this->border_style.border_right:
//             content_width -= $this->border_style.border_width
//         if $this->border_style.border_top and\
//                 $this->render_element_type in (RenderElementType.first, RenderElementType.complete):
//             content_y += $this->border_style.border_width
//             content_height -= $this->border_style.border_width
//         if $this->border_style.border_bottom and\
//                 $this->render_element_type in (RenderElementType.last, RenderElementType.complete):
//             content_height -= $this->border_style.border_width

//         if not $this->background_color.transparent:
//             pdf_doc.set_fill_color($this->background_color.r, $this->background_color.g, $this->background_color.b)
//             pdf_doc.rect(content_x, content_y, content_width, content_height, style='F')

//         render_y = y
//         if $this->border_style.border_top and\
//                 $this->render_element_type in (RenderElementType.first, RenderElementType.complete):
//             render_y += $this->border_style.border_width
//         for element in $this->elements:
//             element.render_pdf(container_offset_x=content_x, container_offset_y=content_y, pdf_doc=pdf_doc)

//         if ($this->border_style.border_left or $this->border_style.border_top or
//                 $this->border_style.border_right or $this->border_style.border_bottom):
//             DocElement.draw_border(
//                 x=x, y=y, width=$this->width, height=height,
//                 render_element_type=$this->render_element_type, border_style=$this->border_style, pdf_doc=pdf_doc)


// class FrameElement(DocElement):
//     function __construct(self, report, data, containers):
//         DocElement.__init__(self, report, data)
//         from .containers import Frame
//         $this->background_color = Color(data.get('backgroundColor'))
//         $this->border_style = BorderStyle(data)
//         $this->print_if = data.get('printIf', '')
//         $this->remove_empty_element = bool(data.get('removeEmptyElement'))
//         $this->shrink_to_content_height = bool(data.get('shrinkToContentHeight'))
//         $this->spreadsheet_hide = bool(data.get('spreadsheet_hide'))
//         $this->spreadsheet_column = get_int_value(data, 'spreadsheet_column')
//         $this->spreadsheet_add_empty_row = bool(data.get('spreadsheet_addEmptyRow'))

//         # rendering_complete status for next page, in case rendering was not started on first page.
//         $this->next_page_rendering_complete = False
//         # container content height of previous page, in case rendering was not started on first page
//         $this->prev_page_content_height = 0

//         $this->render_element_type = RenderElementType.none
//         $this->container = Frame(
//             width=$this->width, height=$this->height,
//             container_id=str(data.get('linkedContainerId')), containers=containers, report=report)

//     def get_used_height(self):
//         height = $this->container.get_render_elements_bottom()
//         if $this->border_style.border_top and $this->render_element_type == RenderElementType.none:
//             height += $this->border_style.border_width
//         if $this->border_style.border_bottom:
//             height += $this->border_style.border_width
//         if $this->render_element_type == RenderElementType.none and not $this->shrink_to_content_height:
//             height = max($this->height, height)
//         return height

//     def prepare(self, ctx, pdf_doc, only_verify):
//         $this->container.prepare(ctx, pdf_doc=pdf_doc, only_verify=only_verify)
//         $this->next_page_rendering_complete = False
//         $this->prev_page_content_height = 0
//         $this->render_element_type = RenderElementType.none

//     def get_next_render_element(self, offset_y, container_height, ctx, pdf_doc):
//         $this->render_y = offset_y
//         content_height = container_height
//         render_element = FrameBlockElement($this->report, self, render_y=offset_y)

//         if $this->border_style.border_top and $this->render_element_type == RenderElementType.none:
//             content_height -= $this->border_style.border_width
//         if $this->border_style.border_bottom:
//             # this is not 100% correct because bottom border is only applied if frame fits
//             # on current page. this should be negligible because the border is usually only a few pixels
//             # and most of the time the frame fits on one page.
//             # to get the exact height in advance would be quite hard and is probably not worth the effort ...
//             content_height -= $this->border_style.border_width

//         if $this->first_render_element:
//             available_height = container_height - offset_y
//             $this->first_render_element = False
//             rendering_complete = $this->container.create_render_elements(
//                 content_height, ctx, pdf_doc)

//             needed_height = $this->get_used_height()

//             if rendering_complete and needed_height <= available_height:
//                 # rendering is complete and all elements of frame fit on current page
//                 $this->rendering_complete = True
//                 $this->render_bottom = offset_y + needed_height
//                 $this->render_element_type = RenderElementType.complete
//                 render_element.add_elements($this->container, $this->render_element_type, needed_height)
//                 return render_element, True
//             else:
//                 if offset_y == 0:
//                     # rendering of frame elements does not fit on current page but
//                     # we are already at the top of the page -> start rendering and continue on next page
//                     $this->render_bottom = offset_y + available_height
//                     $this->render_element_type = RenderElementType.first
//                     render_element.add_elements($this->container, $this->render_element_type, available_height)
//                     return render_element, False
//                 else:
//                     # rendering of frame elements does not fit on current page -> start rendering on next page
//                     $this->next_page_rendering_complete = rendering_complete
//                     $this->prev_page_content_height = content_height
//                     return None, False

//         if $this->render_element_type == RenderElementType.none:
//             # render elements were already created on first call to get_next_render_element
//             # but elements did not fit on first page

//             if content_height == $this->prev_page_content_height:
//                 # we don't have to create any render elements here because we can use
//                 # the previously created elements

//                 $this->rendering_complete = $this->next_page_rendering_complete
//             else:
//                 # we cannot use previously created render elements because container height is different
//                 # on current page. this should be very unlikely but could happen when the frame should be
//                 # printed on the first page and header/footer are not shown on first page, i.e. the following
//                 # pages have a different content band size than the first page.

//                 $this->container.prepare(ctx, pdf_doc=pdf_doc)
//                 $this->rendering_complete = $this->container.create_render_elements(content_height, ctx, pdf_doc)
//         else:
//             $this->rendering_complete = $this->container.create_render_elements(content_height, ctx, pdf_doc)
//         $this->render_bottom = offset_y + $this->get_used_height()

//         if not $this->rendering_complete:
//             # use whole size of container if frame is not rendered completely
//             $this->render_bottom = offset_y + container_height

//             if $this->render_element_type == RenderElementType.none:
//                 $this->render_element_type = RenderElementType.first
//             else:
//                 $this->render_element_type = RenderElementType.between
//         else:
//             if $this->render_element_type == RenderElementType.none:
//                 $this->render_element_type = RenderElementType.complete
//             else:
//                 $this->render_element_type = RenderElementType.last
//         render_element.add_elements($this->container, $this->render_element_type, $this->get_used_height())
//         return render_element, $this->rendering_complete

//     def render_spreadsheet(self, row, col, ctx, renderer):
//         if $this->spreadsheet_column:
//             col = $this->spreadsheet_column - 1
//         row, col = $this->container.render_spreadsheet(row, col, ctx, renderer)
//         if $this->spreadsheet_add_empty_row:
//             row += 1
//         return row, col

//     def cleanup(self):
//         $this->container.cleanup()


// class SectionBandElement(object):
//     function __construct(self, report, data, band_type, containers):
//         from .containers import Container
//         assert(isinstance(data, dict))
//         $this->id = data.get('id', '')
//         $this->width = report.document_properties.page_width -\
//             report.document_properties.margin_left - report.document_properties.margin_right
//         $this->height = get_int_value(data, 'height')
//         $this->band_type = band_type
//         if band_type == BandType.header:
//             $this->repeat_header = bool(data.get('repeatHeader'))
//             $this->always_print_on_same_page = True
//         else:
//             $this->repeat_header = None
//             $this->always_print_on_same_page = bool(data.get('alwaysPrintOnSamePage'))
//         $this->shrink_to_content_height = bool(data.get('shrinkToContentHeight'))

//         $this->container = Container(
//             container_id=str(data.get('linkedContainerId')), containers=containers, report=report)
//         $this->container.width = $this->width
//         $this->container.height = $this->height
//         $this->container.allow_page_break = False
//         $this->rendering_complete = False
//         $this->prepare_container = True
//         $this->rendered_band_height = 0

//     def prepare(self, ctx, pdf_doc, only_verify):
//         pass

//     def create_render_elements(self, offset_y, container_height, ctx, pdf_doc):
//         available_height = container_height - offset_y
//         if $this->always_print_on_same_page and not $this->shrink_to_content_height and\
//                 (container_height - offset_y) < $this->height:
//             # not enough space for whole band
//             $this->rendering_complete = False
//         else:
//             if $this->prepare_container:
//                 $this->container.prepare(ctx, pdf_doc)
//                 $this->rendered_band_height = 0
//             else:
//                 $this->rendered_band_height += $this->container.used_band_height
//                 # clear render elements from previous page
//                 $this->container.clear_rendered_elements()
//             $this->rendering_complete = $this->container.create_render_elements(available_height, ctx=ctx, pdf_doc=pdf_doc)

//         if $this->rendering_complete:
//             remaining_min_height = $this->height - $this->rendered_band_height
//             if not $this->shrink_to_content_height and $this->container.used_band_height < remaining_min_height:
//                 # rendering of band complete, make sure band is at least as large
//                 # as minimum height (even if it spans over more than 1 page)
//                 if remaining_min_height <= available_height:
//                     $this->prepare_container = True
//                     $this->container.used_band_height = remaining_min_height
//                 else:
//                     # minimum height is larger than available space, continue on next page
//                     $this->rendering_complete = False
//                     $this->prepare_container = False
//                     $this->container.used_band_height = available_height
//             else:
//                 $this->prepare_container = True
//         else:
//             if $this->always_print_on_same_page:
//                 # band must be printed on same page but available space is not enough,
//                 # try to render it on top of next page
//                 $this->prepare_container = True
//                 if offset_y == 0:
//                     field = 'size' if $this->band_type == BandType.header else 'always_print_on_same_page'
//                     raise ReportBroError(
//                         Error('errorMsgSectionBandNotOnSamePage', object_id=$this->id, field=field))
//             else:
//                 $this->prepare_container = False
//                 $this->container.first_element_offset_y = available_height
//                 $this->container.used_band_height = available_height

//     def get_used_band_height(self):
//         return $this->container.used_band_height

//     def get_render_elements(self):
//         return $this->container.render_elements


// class SectionBlockElement(DocElementBase):
//     function __construct(self, report, render_y):
//         DocElementBase.__init__(self, report, dict(y=0))
//         $this->report = report
//         $this->render_y = render_y
//         $this->render_bottom = render_y
//         $this->height = 0
//         $this->bands = []
//         $this->complete = False

//     def is_empty(self):
//         return len($this->bands) == 0

//     def add_section_band(self, section_band):
//         if section_band.rendering_complete or not section_band.always_print_on_same_page:
//             band_height = section_band.get_used_band_height()
//             $this->bands.append(dict(height=band_height, elements=list(section_band.get_render_elements())))
//             $this->height += band_height
//             $this->render_bottom += band_height

//     def render_pdf(self, container_offset_x, container_offset_y, pdf_doc):
//         y = $this->render_y + container_offset_y
//         for band in $this->bands:
//             for element in band['elements']:
//                 element.render_pdf(container_offset_x=container_offset_x, container_offset_y=y, pdf_doc=pdf_doc)
//             y += band['height']


// class SectionElement(DocElement):
//     function __construct(self, report, data, containers):
//         DocElement.__init__(self, report, data)
//         $this->data_source = data.get('dataSource', '')
//         $this->print_if = data.get('printIf', '')

//         header = bool(data.get('header'))
//         footer = bool(data.get('footer'))
//         if header:
//             $this->header = SectionBandElement(report, data.get('headerData'), BandType.header, containers)
//         else:
//             $this->header = None
//         $this->content = SectionBandElement(report, data.get('contentData'), BandType.content, containers)
//         if footer:
//             $this->footer = SectionBandElement(report, data.get('footerData'), BandType.footer, containers)
//         else:
//             $this->footer = None
//         $this->print_header = $this->header is not None

//         $this->x = 0
//         $this->width = 0
//         $this->height = $this->content.height
//         if $this->header:
//             $this->height += $this->header.height
//         if $this->footer:
//             $this->height += $this->footer.height
//         $this->bottom = $this->y + $this->height

//         $this->data_source_parameter = None
//         $this->row_parameters = dict()
//         $this->rows = []
//         $this->row_count = 0
//         $this->row_index = -1

//     def prepare(self, ctx, pdf_doc, only_verify):
//         parameter_name = Context.strip_parameter_name($this->data_source)
//         $this->data_source_parameter = ctx.get_parameter(parameter_name)
//         if not $this->data_source_parameter:
//             raise ReportBroError(
//                 Error('errorMsgMissingDataSourceParameter', object_id=$this->id, field='data_source'))
//         if $this->data_source_parameter.type != ParameterType.array:
//             raise ReportBroError(
//                 Error('errorMsgInvalidDataSourceParameter', object_id=$this->id, field='data_source'))
//         for row_parameter in $this->data_source_parameter.children:
//             $this->row_parameters[row_parameter.name] = row_parameter
//         $this->rows, parameter_exists = ctx.get_data($this->data_source_parameter.name)
//         if not parameter_exists:
//             raise ReportBroError(
//                 Error('errorMsgMissingData', object_id=$this->id, field='data_source'))
//         if not isinstance($this->rows, list):
//             raise ReportBroError(
//                 Error('errorMsgInvalidDataSource', object_id=$this->id, field='data_source'))

//         $this->row_count = len($this->rows)
//         $this->row_index = 0

//         if only_verify:
//             if $this->header:
//                 $this->header.prepare(ctx, pdf_doc=None, only_verify=True)
//             while $this->row_index < $this->row_count:
//                 # push data context of current row so values of current row can be accessed
//                 ctx.push_context($this->row_parameters, $this->rows[$this->row_index])
//                 $this->content.prepare(ctx, pdf_doc=None, only_verify=True)
//                 ctx.pop_context()
//                 $this->row_index += 1
//             if $this->footer:
//                 $this->footer.prepare(ctx, pdf_doc=None, only_verify=True)

//     def get_next_render_element(self, offset_y, container_height, ctx, pdf_doc):
//         $this->render_y = offset_y
//         $this->render_bottom = $this->render_y
//         render_element = SectionBlockElement($this->report, render_y=offset_y)

//         if $this->print_header:
//             $this->header.create_render_elements(offset_y, container_height, ctx, pdf_doc)
//             render_element.add_section_band($this->header)
//             if not $this->header.rendering_complete:
//                 return render_element, False
//             if not $this->header.repeat_header:
//                 $this->print_header = False

//         while $this->row_index < $this->row_count:
//             # push data context of current row so values of current row can be accessed
//             ctx.push_context($this->row_parameters, $this->rows[$this->row_index])
//             $this->content.create_render_elements(offset_y + render_element.height, container_height, ctx, pdf_doc)
//             ctx.pop_context()
//             render_element.add_section_band($this->content)
//             if not $this->content.rendering_complete:
//                 return render_element, False
//             $this->row_index += 1

//         if $this->footer:
//             $this->footer.create_render_elements(offset_y + render_element.height, container_height, ctx, pdf_doc)
//             render_element.add_section_band($this->footer)
//             if not $this->footer.rendering_complete:
//                 return render_element, False

//         # all bands finished
//         $this->rendering_complete = True
//         $this->render_bottom += render_element.height
//         return render_element, True

//     def render_spreadsheet(self, row, col, ctx, renderer):
//         if $this->header:
//             row, _ = $this->header.container.render_spreadsheet(row, col, ctx, renderer)
//         row, _ = $this->content.container.render_spreadsheet(row, col, ctx, renderer)
//         if $this->footer:
//             row, _ = $this->footer.container.render_spreadsheet(row, col, ctx, renderer)
//         return row, col

//     def cleanup(self):
//         if $this->header:
//             $this->header.container.cleanup()
//         $this->content.container.cleanup()
//         if $this->footer:
//             $this->footer.container.cleanup()
