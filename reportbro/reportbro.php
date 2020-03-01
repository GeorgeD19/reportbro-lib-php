<?php
#
// Copyright (C) 2020 George Dunlop
#
// This file is a port of the reportbro-lib-php, a library to generate PDF and Excel reports.
// Demos can be found at https://www.reportbro.com
#
// Dual licensed under AGPLv3 and ReportBro commercial license:
// https://www.reportbro.com/license
#
// You should have received a copy of the GNU Affero General Public License
// along with this program. If not, see https://www.gnu.org/licenses/
#
// Details for ReportBro commercial license can be found at
// https://www.reportbro.com/license/agreement
#

define('FPDF_FONTPATH', __DIR__ . '/font');
define('CURRENCY', array(
    '€' => chr(128),
    '$' => chr(590),
    '£' => chr(163)
));

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/containers.php';
require_once __DIR__ . '/elements.php';
require_once __DIR__ . '/enums.php';
require_once __DIR__ . '/structs.php';
require_once __DIR__ . '/utils.php';

use Fpdf\Fpdf;

class DocumentPDFRenderer {
    function __construct($header_band, $content_band, $footer_band, $report, $context, $additional_fonts, $filename, $add_watermark) {
        $this->header_band = $header_band;
        $this->content_band = $content_band;
        $this->footer_band = $footer_band;
        $this->document_properties = $report->document_properties;
        $this->pdf_doc = new FPDFRB($report->document_properties, $additional_fonts);
        $this->pdf_doc->AddFont('tangerine', '', 'tangerine.php');
        $this->pdf_doc->AddFont('firefly', '', 'firefly.php');
        $this->pdf_doc->AddFont('futurabkbt', '', 'futurabkbt.php');
        $this->pdf_doc->SetMargins(0, 0);
        $this->pdf_doc->c_margin = 0; // interior cell margin
        $this->context = $context;
        $this->filename = $filename;
        $this->add_watermark = $add_watermark;
    }

    function add_page() {
        $this->pdf_doc->AddPage();
        $this->context->inc_page_number();
    }

    function is_finished() {
        return $this->content_band->is_finished();
    }

    function render() {
        $watermark_width = $watermark_height = 0;
        $watermark_filename = __DIR__ . '/data/logo_watermark.png';
        $this->add_watermark = true;
        if ($this->add_watermark) {
            if (!file_exists($watermark_filename)) {
                $this->add_watermark = false;
            } else {
                $watermark_width = $this->document_properties->page_width / 3;
                $watermark_height = $watermark_width * (108 / 461);
            }
        }

        $this->content_band->prepare($this->context, $this->pdf_doc);
        $page_count = 1;
        while (true) {
            $height = $this->document_properties->page_height - $this->document_properties->margin_top - $this->document_properties->margin_bottom;
            if ($this->document_properties->header_display == BandDisplay::always() || ($this->document_properties->header_display == BandDisplay::not_on_first_page() && $page_count != 1)) {
                $height -= $this->document_properties->header_size;
            }
            if ($this->document_properties->footer_display == BandDisplay::always() || ($this->document_properties->footer_display == BandDisplay::not_on_first_page() && $page_count != 1)) {
                $height -= $this->document_properties->footer_size;
            }
            $complete = $this->content_band->create_render_elements($height, $this->context, $this->pdf_doc);
            if ($complete) {
                break;
            }
            $page_count += 1;
            if ($page_count >= 10000) {
                throw new Exception('Too many pages (probably an endless loop)');
            }
        }
        $this->context->set_page_count($page_count);

        $footer_offset_y = $this->document_properties->page_height - $this->document_properties->footer_size - $this->document_properties->margin_bottom;
        // render at least one page to show header/footer even if content is empty
        while (!$this->content_band->is_finished() || $this->context->get_page_number() == 0) {
            $this->add_page();
            if ($this->add_watermark) {
                if ($watermark_height < $this->document_properties->page_height) {
                    $this->pdf_doc->Image($watermark_filename, $this->document_properties->page_width / 2 - $watermark_width / 2, $this->document_properties->page_height - $watermark_height, $watermark_width, $watermark_height);
                }
            }
            $content_offset_y = $this->document_properties->margin_top;
            $page_number = $this->context->get_page_number();
            if ($this->document_properties->header_display == BandDisplay::always() || ($this->document_properties->header_display == BandDisplay::not_on_first_page() && $page_number != 1)) {
                $content_offset_y += $this->document_properties->header_size;
                $this->header_band->prepare($this->context, $this->pdf_doc);
                $this->header_band->create_render_elements($this->document_properties->header_size, $this->context, $this->pdf_doc);
                $this->header_band->render_pdf($this->document_properties->margin_left, $this->document_properties->margin_top, $this->pdf_doc);
            }
            if ($this->document_properties->footer_display == BandDisplay::always() || ($this->document_properties->footer_display == BandDisplay::not_on_first_page() && $page_number != 1)) {
                $this->footer_band->prepare($this->context, $this->pdf_doc);
                $this->footer_band->create_render_elements($this->document_properties->footer_size, $this->context, $this->pdf_doc);
                $this->footer_band->render_pdf($this->document_properties->margin_left, $footer_offset_y, $this->pdf_doc);
            }

            $this->content_band->render_pdf($this->document_properties->margin_left, $content_offset_y, $this->pdf_doc, true);
        }
        $this->header_band->cleanup();
        $this->footer_band->cleanup();
        $dest = $this->filename ? 'F' : 'S';
        return $this->pdf_doc->output($this->filename, $dest);
    }
}

class DocumentXLSXRenderer {
    function __construct($header_band, $content_band, $footer_band, $report, $context, $filename) {
        $this->header_band = $header_band;
        $this->content_band = $content_band;
        $this->footer_band = $footer_band;
        $this->document_properties = $report->document_properties;
        // $this->workbook_mem = BytesIO()
        // $this->workbook = $xlsxwriter->Workbook($filename ? $filename : $this->workbook_mem);
        $this->worksheet = $this->workbook->add_worksheet();
        $this->context = $context;
        $this->filename = $filename;
        $this->row = 0;
        $this->column_widths = array();
    }

    function render() {
        if ($this->document_properties->header_display != BandDisplay::never()) {
            $this->render_band($this->header_band);
        }
        $this->render_band($this->content_band);
        if ($this->document_properties->header_display != BandDisplay::never()) {
            $this->render_band($this->footer_band);
        }

        foreach ($this->column_widths as $i => $column_width) {
            if ($column_width > 0) {
                // setting the column width is just an approximation, in Excel the width
                // is the number of characters in the default font
                $this->worksheet->set_column($i, $i, $column_width / 7);
            }
        }

        $this->workbook->close();
        if (!$this->filename) {
            // if no filename is given the spreadsheet data will be returned
            $this->workbook_mem->seek(0);
            return $this->workbook_mem->read();
        }
        return null;
    }

    function render_band($band) {
        $band->prepare($this->context);
        list($this->row) = $band->render_spreadsheet($this->row, 0, $this->context, $this);
    }

    function update_column_width($col, $width) {
        if ($col >= count($this->column_widths)) {
            // make sure column_width list contains entries for each column
            $this->column_widths = array_merge($this->column_widths, array_fill(0, ($col + 1 - count($this->column_widths)), [-1]));
        }
        if ($width > $this->column_widths[$col]) {
            $this->column_widths[$col] = $width;
        }
    }

    function write($row, $col, $colspan, $text, $cell_format, $width) {
        if ($colspan > 1) {
            $this->worksheet->merge_range($row, $col, $row, $col + $colspan - 1, $text, $cell_format);
        } else {
            $this->worksheet->write($row, $col, $text, $cell_format);
            $this->update_column_width($col, $width);
        }
    }

    function insert_image($row, $col, $image_filename, $width) {
        $this->worksheet->insert_image($row, $col, $image_filename);
        $this->update_column_width($col, $width);
    }

    function add_format($format_props) {
        return $this->workbook->add_format($format_props);
    }
}

class DocumentProperties {
    function __construct($report, $data) {
        $this->id = '0_document_properties';
        $this->page_format = PageFormat::byName(strtolower($data->{'pageFormat'}));
        $this->orientation = Orientation::byName($data->{'orientation'});
        $this->report = $report;

        if ($this->page_format == PageFormat::a4()) {
            if ($this->orientation == Orientation::portrait()) {
                $this->page_width = 210;
                $this->page_height = 297;
            } else {
                $this->page_width = 297;
                $this->page_height = 210;
            }
            $unit = Unit::mm();
        } else if ($this->page_format == PageFormat::a5()) {
            if ($this->orientation == Orientation::portrait()) {
                $this->page_width = 148;
                $this->page_height = 210;
            } else {
                $this->page_width = 210;
                $this->page_height = 148;
            }
            $unit = Unit::mm();
        } else if ($this->page_format == PageFormat::letter()) {
            if ($this->orientation == Orientation::portrait()) {
                $this->page_width = 8.5;
                $this->page_height = 11;
            } else {
                $this->page_width = 11;
                $this->page_height = 8.5;
            }
            $unit = Unit::inch();
        } else {
            $this->page_width = intval($data->{'pageWidth'});
            $this->page_height = intval($data->{'pageHeight'});
            $unit = Unit::byName($data->{'unit'});
            if ($unit == Unit::mm()) {
                if ($this->page_width < 100 or $this->page_width >= 100000) {
                    array_push($this->report->errors, Error('errorMsgInvalidPageSize', $this->id, 'page'));
                } else if ($this->page_height < 100 or $this->page_height >= 100000) {
                    array_push($this->report->errors, Error('errorMsgInvalidPageSize', $this->id, 'page'));
                }
            } else if ($unit == Unit::inch()) {
                if ($this->page_width < 1 or $this->page_width >= 1000) {
                    array_push($this->report->errors, Error('errorMsgInvalidPageSize', $this->id, 'page'));
                } else if ($this->page_height < 1 or $this->page_height >= 1000) {
                    array_push($this->report->errors, Error('errorMsgInvalidPageSize', $this->id, 'page'));
                }
            }
        }
        $dpi = 72;
        if ($unit == Unit::mm()) {
            $this->page_width = round(($dpi * $this->page_width) / 25.4);
            $this->page_height = round(($dpi * $this->page_height) / 25.4);
        } else {
            $this->page_width = round($dpi * $this->page_width);
            $this->page_height = round($dpi * $this->page_height);
        }

        $this->content_height = intval($data->{'contentHeight'});
        $this->margin_left = intval($data->{'marginLeft'});
        $this->margin_top = intval($data->{'marginTop'});
        $this->margin_right = intval($data->{'marginRight'});
        $this->margin_bottom = intval($data->{'marginBottom'});
        $this->pattern_locale = property_exists($data, 'patternLocale') ? $data->{'patternLocale'} : '';
        $this->pattern_currency_symbol = property_exists($data, 'patternCurrencySymbol') ? $data->{'patternCurrencySymbol'} : '';
        if (!in_array($this->pattern_locale, array('de', 'en', 'es', 'fr', 'it'))) {
            throw new Exception('invalid pattern_locale');
        }

        $this->header = boolval($data->{'header'});
        if ($this->header) {
            $this->header_display = BandDisplay::byName($data->{'headerDisplay'});
            $this->header_size = intval($data->{'headerSize'});
        } else {
            $this->header_display = BandDisplay::never();
            $this->header_size = 0;
        }
        $this->footer = boolval($data->{'footer'});
        if ($this->footer) {
            $this->footer_display = BandDisplay::byName($data->{'footerDisplay'});
            $this->footer_size = intval($data->{'footerSize'});
        } else {
            $this->footer_display = BandDisplay::never();
            $this->footer_size = 0;
        }
        if ($this->content_height == 0) {
            $this->content_height = $this->page_height - $this->header_size - $this->footer_size - $this->margin_top - $this->margin_bottom;
        }
    }
}

class FPDFRB extends Fpdf {
    function __construct($document_properties, $additional_fonts) {
        if ($document_properties->orientation == Orientation::portrait()) {
            $orientation = 'P';
            $dimension = array($document_properties->page_width, $document_properties->page_height);
        } else {
            $orientation = 'L';
            $dimension = array($document_properties->page_height, $document_properties->page_width);
        }
        parent::__construct($orientation, 'pt', $dimension);
        $this->x = 0;
        $this->y = 0;
        // $this->set_doc_option('core_fonts_encoding', 'windows-1252');
        $this->loaded_images = array();
        $this->available_fonts = array(
            "courier"=>(object)array("standard_font"=>true),
            "helvetica"=>(object)array("standard_font"=>true),
            "times"=>(object)array("standard_font"=>true));
        if ($additional_fonts) {
            foreach ($additional_fonts as $additional_font) {
                $filename = property_exists($additional_font, 'filename') ? $additional_font->{'filename'} : '';
                $style_map = (object)array(''=>'', 'B'=>'B', 'I'=>'I', 'BI'=>'BI');
                $font = (object)array("standard_font"=>false, "added"=>false, "regular_filename"=>$filename,
                        "bold_filename"=>property_exists($additional_font, 'bold_filename') ? $additional_font->{'bold_filename'} : $filename,
                        "italic_filename"=>property_exists($additional_font, 'italic_filename') ? $additional_font->{'italic_filename'} : $filename,
                        "bold_italic_filename"=>property_exists($additional_font, 'bold_italic_filename') ? $additional_font->{'bold_italic_filename'} : $filename,
                        "style_map"=>$style_map, "uni"=>property_exists($additional_font, 'uni') ? $additional_font->{'uni'} : true);
                // map styles in case there are no separate font-files for bold, italic or bold italic
                // to avoid adding the same font multiple times to the pdf document
                if ($font['bold_filename'] == $font['regular_filename']) {
                    $style_map['B'] = '';
                }
                if ($font['italic_filename'] == $font['bold_filename']) {
                    $style_map['I'] = $style_map['B'];
                } else if ($font['italic_filename'] == $font['regular_filename']) {
                    $style_map['I'] = '';
                }
                if ($font['bold_italic_filename'] == $font['italic_filename']) {
                    $style_map['BI'] = $style_map['I'];
                } else if ($font['bold_italic_filename'] == $font['bold_filename']) {
                    $style_map['BI'] = $style_map['B'];
                } else if ($font['bold_italic_filename'] == $font['regular_filename']) {
                    $style_map['BI'] = '';
                }
                $font['style2filename'] = (object)array(''=>$filename, 'B'=>$font['bold_filename'],
                        'I'=>$font['italic_filename'], 'BI'=>$font['bold_italic_filename']);
                $this->available_fonts[property_exists($additional_font, 'value') ? $additional_font->{'value'} : ''] = $font;
            }
        }
    }

    function _SplitLines(&$txt, $w) {
        // Function contributed by Bruno Michel
        $lines = array();
        $cw = $this->CurrentFont['cw'];
        $wmax = intval(ceil(($w - 2*$this->cMargin) * 1000 / $this->FontSize));
        $s = str_replace("\\r", "",$txt);
        $nb = strlen($s);
        while ($nb > 0 && $s[$nb-1] == "\n") {
            $nb--;
        }
        $s = substr($s, 0, $nb);
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        while ($i < $nb) {
            $c = $s[$i];
            $l += $cw[$c];
            if ($c == " " || $c == "\t" || $c == "\n") {
                $sep = $i;
            }
            if ($c == "\n" || $l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                    $sep = $i;
                } else {
                    $i = $sep + 1;
                }
                array_push($lines, substr($s, $j, $sep));
                $sep = -1;
                $j = $i;
                $l = 0;
            } else {
                $i++;
            }
        }
        if ($i != $j) {
            array_push($lines, substr($s, $j, $i));
        }

        return $lines;
    }

    function SplitLines(&$txt, $w) {
        $lines = array();
        $tmpLines = explode("\n", $txt);
        foreach ($tmpLines as $line) {
            if ($line == "") {
                array_push($lines, "");
            } else {
                $lines = array_merge($lines, $this->_SplitLines($line, $w));
            }
        }
        return $lines;
    }

    function add_image($img, $image_key) {
        $this->loaded_images[$image_key] = $img;
    }

    function get_image($image_key) {
        return $this->loaded_images->{$image_key};
    }

    function set_font($family, $style = '', $size = 0, $underline = false) {
        $font = $this->available_fonts[$family];
        if ($font) {
            if (!$font->{'standard_font'}) {
                if ($style) {
                    // replace of 'U' is needed because it is set for underlined text
                    // when called from FPDF->add_page
                    $style = $font['style_map']->{str_replace('U', '', $style)};
                }
                if (!$font['added']) {
                    $filename = $font['style2filename']->{$style};
                    $this->AddFont($family, $style, $filename, $font['uni']);
                    $font['added'] = true;
                }
            }
            if ($underline) {
                $style += 'U';
            }
            parent::SetFont($family, $style, $size);
        }
    }
}

class Report {
    function __construct($report_definition, $data, $is_test_data = false, $additional_fonts = null) {
        if (!is_object($report_definition) || !is_object($data)) {
            throw new Exception(); return;
        }

        $this->errors = array();

        $this->document_properties = new DocumentProperties($this, property_exists($report_definition, 'documentProperties') ? $report_definition->{'documentProperties'} : json_decode("{}"), json_decode("{}"));

        $this->containers = array();
        $this->header = new ReportBand(BandType::header(), '0_header', $this->containers, $this);
        $this->content = new ReportBand(BandType::content(), '0_content', $this->containers, $this);
        $this->footer = new ReportBand(BandType::footer(), '0_footer', $this->containers, $this);

        $this->parameters = array();
        $this->styles = array();
        $this->data = array();
        $this->is_test_data = $is_test_data;

        $this->additional_fonts = $additional_fonts;

        $version = $report_definition->{'version'};
        if (is_int($version)) {
            // convert old report definitions
            if ($version < 2) {
                foreach ($report_definition->{'docElements'} as $doc_element) {
                    if (DocElementType::byName($doc_element->{'elementType'}) == DocElementType::table()) {
                        $doc_element['contentDataRows'] = array($doc_element->{'contentData'});
                    }
                }
            }
        }

        // list is needed to compute parameters (parameters with expression) in given order
        $parameter_list = array();
        foreach ($report_definition->{'parameters'} as $item) {
            $parameter = new Parameter($this, $item);
            if (in_array($parameter->name, $this->parameters)) {
                array_push($this->errors, new StandardError('errorMsgDuplicateParameter', $parameter->id, 'name'));
            }
            $this->parameters[$parameter->name] = $parameter;
            array_push($parameter_list, $parameter);
        }

        foreach ($report_definition->{'styles'} as $item) {
            $style = new TextStyle($item);
            $style_id = intval($item->{'id'});
            $this->styles[$style_id] = $style;
        }
        
        foreach ($report_definition->{'docElements'} as $doc_element) {
            $element_type = DocElementType::byName($doc_element->{'elementType'});
            $container_id = strval($doc_element->{'containerId'});
            $container = null;
            if ($container_id) {
                $container = $this->containers[$container_id];
            }
            $elem = null;
            if ($element_type == DocElementType::text()) {
                $elem = new TextElement($this, $doc_element);
            } else if ($element_type == DocElementType::line()) {
                $elem = new LineElement($this, $doc_element);
            } else if ($element_type == DocElementType::image()) {
                $elem = new ImageElement($this, $doc_element);
            } else if ($element_type == DocElementType::bar_code()) {
                // $elem = new BarCodeElement($this, $doc_element);
            } else if ($element_type == DocElementType::table()) {
                $elem = new TableElement($this, $doc_element);
            } else if ($element_type == DocElementType::page_break()) {
                $elem = new PageBreakElement($this, $doc_element);
            } else if ($element_type == DocElementType::frame()) {
                $elem = new FrameElement($this, $doc_element, $this->containers);
            } else if ($element_type == DocElementType::section()) {
                $elem = new SectionElement($this, $doc_element, $this->containers);
            }

            if ($elem && $container) {
                if ($container->is_visible()) {
                    if ($elem->x < 0) {
                        array_push($this->errors, new StandardError('errorMsgInvalidPosition', $elem->id, 'position'));
                    } else if ($elem->x + $elem->width > $container->width) {
                        array_push($this->errors, new StandardError('errorMsgInvalidSize', $elem->id, 'position'));
                    }
                    if ($elem->y < 0) {
                        array_push($this->errors, new StandardError('errorMsgInvalidPosition', $elem->id, 'position'));
                    } else if ($elem->y + $elem->height > $container->height) {
                        array_push($this->errors, new StandardError('errorMsgInvalidSize', $elem->id, 'position'));
                    }
                }
                $container->add($elem);
            }
        }

        $this->context = new Context($this, $this->parameters, $this->data);

        $computed_parameters = array();
        $this->process_data($this->data, $data, $parameter_list, $is_test_data, $computed_parameters, array());
        try {
            if (!$this->errors) {
                $this->compute_parameters($computed_parameters, $this->data);
            }
        } catch (Exception $err) {
            array_push($this->errors, $err);
        }
    }

    function generate_pdf($filename = '', $add_watermark = false) {
        $renderer = new DocumentPDFRenderer($this->header, $this->content, $this->footer, $this, $this->context, $this->additional_fonts, $filename, $add_watermark);
        return $renderer->render();
    }

    function generate_xlsx($filename = '') {
        $renderer = new DocumentXLSXRenderer($this->header, $this->content, $this->footer, $this,  $this->context, $filename);
        return $renderer->render();
    }

    // goes through all elements in header, content and footer and throws a ReportBroError in case
    // an element is invalid
    function verify() {
        if ($this->document_properties->header_display != BandDisplay::never()) {
            $this->header->prepare($this->context, true);
        }
        $this->content->prepare($this->context, true);
        if ($this->document_properties->header_display != BandDisplay::never()) {
            $this->footer->prepare($this->context, true);
        }
    }

    function parse_parameter_value($parameter, $parent_id, $is_test_data, $parameter_type, $value) {
        $error_field = $is_test_data ? 'test_data' : 'type';
        if ($parameter_type == ParameterType::string()) {
            if ($value != null) {
                if (!is_string($value)) {
                    throw new Error('value of parameter {name} must be str type (unicode for Python 2.7.x)' . $parameter->name);
                }
            } else if (!$parameter->nullable) {
                $value = '';
            }
        } else if ($parameter_type == ParameterType::number()) {
            if ($value) {
                if (is_string($value)) {
                    $value = str_replace(',', '.', $value);
                }
                try {
                    $value = floatval($value);
                } catch (Exception $e) {
                    if ($parent_id && $is_test_data) {
                        array_push($this->errors, new StandardError('errorMsgInvalidTestData', $parent_id, 'test_data'));
                        array_push($this->errors, new StandardError('errorMsgInvalidNumber', $parameter->id, 'type'));
                    } else {
                        array_push($this->errors, new StandardError('errorMsgInvalidNumber',$parameter->id, $error_field, $parameter->name));
                    }
                }
            } else if ($value != null) {
                if (is_numeric($value)) {
                    $value = floatval(0);
                } else if ($is_test_data && is_string($value)) {
                    $value = $parameter->nullable ? null : floatval(0);
                } else if (!is_float($value)) {
                    if ($parent_id && $is_test_data) {
                        array($this->errors, new StandardError('errorMsgInvalidTestData', $parent_id, 'test_data'));
                        array($this->errors, new StandardError('errorMsgInvalidNumber', $parameter->id, 'type'));
                    } else {
                        array($this->errors, new StandardError('errorMsgInvalidNumber', $parameter->id, $error_field, $parameter->name));
                    }
                }
            } else if (!$parameter->nullable) {
                $value = floatval(0);
            }
        } else if ($parameter_type == ParameterType::boolean()) {
            if ($value != null) {
                $value = boolval($value);
            } else if (!$parameter->nullable) {
                $value = false;
            }
        } else if ($parameter_type == ParameterType::date()) {
            if (is_string($value)) {
                if ($is_test_data && !$value) {
                    $value = $parameter->nullable ? null : _get_datetime(null);
                } else {
                    try {
                        $format = 'Y-m-d H:i:s';
                        $colon_count = substr_count($value, ":");
                        $dash_count = substr_count($value, "-");
                        if ($colon_count == 0) {
                            $value .= " 00:00:00";
                        } else if ($colon_count == 1) {
                            $value .= ":00";
                        }
                        if ($dash_count == 0) {
                            $value = '0001-01-01 ' + $value;
                        }
                        $value = DateTime::createFromFormat($format, $value);
                    } catch (Exception $e) {
                        if ($parent_id && $is_test_data) {
                            array_push($this->errors, new StandardError('errorMsgInvalidTestData', $parent_id, 'test_data'));
                            array_push($this->errors, new StandardError('errorMsgInvalidDate', $parameter->id, 'type'));
                        } else {
                            array_push($this->errors, new StandardError('errorMsgInvalidDate', $parameter->id, $error_field, $parameter->name));
                        }
                    }
                }
            } else if (is_date($value)) {
                $value = format_date($value);
            } else if ($value != null) {
                if ($parent_id && $is_test_data) {
                    array_push($this->errors, new StandardError('errorMsgInvalidTestData', $parent_id, 'test_data'));
                    array_push($this->errors, new StandardError('errorMsgInvalidDate', $parameter->id, 'type'));
                } else {
                    array_push($this->errors, new StandardError('errorMsgInvalidDate', $parameter->id, $error_field, $parameter->name));
                }
            } else if (!$parameter->nullable) {
                $value = _get_datetime(null);
            }
        }
        // $rv = iconv('UTF-8', 'windows-1252', $value);
        return $value;
    }

    function process_data(&$dest_data, $src_data, $parameters, $is_test_data, &$computed_parameters, $parents) {
        $field = $is_test_data ? 'test_data' : 'type';
        $parent_id = $parents ? end($parents)->id : null;
        foreach ($parameters as $parameter) {
            if ($parameter->is_internal) {
                continue;
            }
            preg_match('~^[^\d\W]\w*$~', $parameter->name, $matches);
            if (!count($matches)) {
                array_push($this->errors, new StandardError('errorMsgInvalidParameterName', $parameter->id, 'name', $parameter->name));
            }
            $parameter_type = $parameter->type;
            if (in_array($parameter_type, array(ParameterType::average(), ParameterType::sum())) || $parameter->eval) {
                if (!$parameter->expression) {
                    array_push($this->errors, new StandardError('errorMsgMissingExpression', $parameter->id, 'expression', $parameter->name));
                } else {
                    $parent_names = array();
                    foreach ($parents as $parent) {
                        array_push($parent_names, $parent->name);
                    }
                    array_push($computed_parameters, array("parameter"=>$parameter, "parent_names"=>$parent_names));
                }
            } else {
                $value = property_exists($src_data, $parameter->name) ? $src_data->{$parameter->name} : null;
                if (in_array($parameter_type, array(ParameterType::string(), ParameterType::number(), ParameterType::boolean(), ParameterType::date()))) {
                    $value = $this->parse_parameter_value($parameter, $parent_id, $is_test_data, $parameter_type, $value);
                } else if (!$parents) {
                    if ($parameter_type == ParameterType::array()) {
                        if (is_array($value)) {
                            array_push($parents, $parameter);
                            $parameter_list = $parameter->fields;
                            // create new list which will be assigned to dest_data to keep src_data unmodified
                            $dest_array = array();

                            foreach ($value as $row) {
                                $dest_array_item = array();
                                $this->process_data($dest_array_item, $row, $parameter_list, $is_test_data, $computed_parameters, $parents);
                                array_push($dest_array, $dest_array_item);
                            }
                            array_pop($parents);
                            $value = $dest_array;
                        } else if ($value == null) {
                            if (!$parameter->nullable) {
                                $value = array();
                            }
                        } else {
                            array_push($this->errors, new StandardError('errorMsgInvalidArray', $parameter->id, $field, $parameter->name));
                        }
                    } else if ($parameter_type == ParameterType::simple_array()) {
                        if (is_array($value)) {
                            $list_values = array();
                            foreach ($value as $list_value) {
                                $parsed_value = $this->parse_parameter_value($parameter, $parent_id, $is_test_data, $parameter->array_item_type, $list_value);
                                array_push($list_values, $parsed_value);
                            }
                            $value = $list_values;
                        } else if ($value == null) {
                            if (!$parameter->nullable) {
                                $value = array();
                            }
                        } else {
                            array_push($this->errors, new StandardError('errorMsgInvalidArray', $parameter->id, $field, $parameter->name));
                        }
                    } else if ($parameter_type == ParameterType::map()) {
                        if ($value == null && !$parameter->nullable) {
                            $value = new stdClass();
                        }
                        if (is_object($value)) {
                            if (is_array($parameter->children)) {
                                array_push($parents, $parameter);
                                // create new dict which will be assigned to dest_data to keep src_data unmodified
                                $dest_map = array();

                                $this->process_data($dest_map, $value, $parameter->children, $is_test_data, $computed_parameters, $parents);
                                array_pop($parents);
                                $value = $dest_map;
                            } else {
                                array_push($this->errors, new StandardError('errorMsgInvalidMap', $parameter->id, 'type', $parameter->name));
                            }
                        } else {
                            array_push($this->errors, new StandardError('errorMsgMissingData', $parameter->id, 'name', $parameter->name));
                        }
                    }
                }
                $dest_data[$parameter->name] = $value;
            }
        }
    }

    function compute_parameters($computed_parameters, &$data) {
        foreach ($computed_parameters as $computed_parameter) {
            $parameter = $computed_parameter['parameter'];
            $value = null;
            if (in_array($parameter->type, array(ParameterType::average(), ParameterType::sum()))) {
                $expr = Context::strip_parameter_name($parameter->expression);
                $pos = strpos($expr, '.');
                if ($pos === false) {
                    array_push($this->errors, new StandardError('errorMsgInvalidAvgSumExpression', $parameter->id, 'expression', $parameter->name));
                } else {
                    $parameter_name = substr($expr, 0, $pos);
                    $parameter_field = substr($expr, $pos+1);
                    $items = array_key_exists($parameter_name, $data) ? $data[$parameter_name] : null;
                    if (!is_array($items)) {
                        array_push($this->errors, new StandardError('errorMsgInvalidAvgSumExpression', $parameter->id, 'expression', $parameter->name));
                    } else {
                        $total = floatval(0);
                        foreach ($items as $item) {
                            $item_value = $item[$parameter_field];
                            if ($item_value === null) {
                                array_push($this->errors, new StandardError('errorMsgInvalidAvgSumExpression', $parameter->id, 'expression', $parameter->name));
                                break;
                            }
                            $total += $item_value;
                        }
                        if ($parameter->type == ParameterType::average()) {
                            $value = $total / count($items);
                        } else if ($parameter->type == ParameterType::sum()) {
                            $value = $total;
                        }
                    }
                }
            } else {
                $value = $this->context->evaluate_expression($parameter->expression, $parameter->id, 'expression');
            }

            $data_entry = &$data;
            $valid = true;
            foreach ($computed_parameter['parent_names'] as $parent_name) {
                $data_entry = &$data_entry[$parent_name];
                if (!is_array($data_entry)) {
                    array_push($this->errors, new StandardError('errorMsgInvalidParameterData', $parameter->id, 'name', $parameter->name));
                    $valid = false;
                }
            }
            if ($valid) {
                $data_entry[$parameter->name] = $value;
            }
        }
    }
}