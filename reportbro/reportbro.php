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

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/containers.php';
require_once __DIR__ . '/elements.php';
require_once __DIR__ . '/enums.php';
require_once __DIR__ . '/structs.php';
require_once __DIR__ . '/utils.php';

use Fpdf\Fpdf;

// regex_valid_identifier = re.compile(r'^[^\d\W]\w*$', re.U)

class DocumentPDFRenderer {
    function __construct($header_band, $content_band, $footer_band, $report, $context, $additional_fonts, $filename, $add_watermark) {
        $this->header_band = $header_band;
        $this->content_band = $content_band;
        $this->footer_band = $footer_band;
        // $this->document_properties = $report->document_properties;
        $this->pdf_doc = new FPDFRB($report->document_properties, $additional_fonts);
//         $this->pdf_doc->set_margins(0, 0);
//         $this->pdf_doc->c_margin = 0; // interior cell margin
//         $this->context = $context;
        $this->filename = $filename;
//         $this->add_watermark = $add_watermark;
    }

    function add_page() {
        $this->pdf_doc->AddPage();
        // $this->context->inc_page_number();
    }

//     function is_finished() {
//         return $this->content_band->is_finished();
//     }

    function render() {
        $watermark_width = $watermark_height = 0;
//         $watermark_filename = $pkg_resources->resource_filename('reportbro', 'data/logo_watermark.png');
//         if ($this->add_watermark) {
//             if (!file_exists($watermark_filename)) {
//                 $this->add_watermark = false;
//             } else {
//                 $watermark_width = $this->document_properties->page_width / 3;
//                 $watermark_height = $watermark_width * (108 / 461);
//             }
//         }
//         $this->content_band->prepare($this->context, $this->pdf_doc);
//         $page_count = 1;
//         while (true) {
//             $height = $this->document_properties->page_height - $this->document_properties->margin_top - $this->document_properties->margin_bottom;
//             if ($this->document_properties->header_display == BandDisplay::always() || ($this->document_properties->header_display == BandDisplay::not_on_first_page() && $page_count != 1)) {
//                 $height -= $this->document_properties->header_size;
//             }
//             if ($this->document_properties->footer_display == BandDisplay::always() || ($this->document_properties->footer_display == BandDisplay::not_on_first_page() && $page_count != 1)) {
//                 $height -= $this->document_properties->footer_size;
//             }
//             $complete = $this->content_band->create_render_elements($height, $this->context, $this->pdf_doc);
//             if ($complete) {
//                 break;
//             }
//             $page_count += 1;
//             if ($page_count >= 10000) {
//                 // throw new Exception('Too many pages (probably an endless loop)');
//             }
//         }
//         $this->context.set_page_count($page_count);

//         $footer_offset_y = $this->document_properties->page_height - $this->document_properties->footer_size - $this->document_properties->margin_bottom;
//         // render at least one page to show header/footer even if content is empty
//         while (!$this->content_band->is_finished() || $this->context->get_page_number() == 0) {
            $this->add_page();
//             if ($this->add_watermark) {
//                 if ($watermark_height < $this->document_properties->page_height) {
//                     $this->pdf_doc->image($watermark_filename, $this->document_properties->page_width / 2 - $watermark_width / 2, $this->document_properties->page_height - $watermark_height, $watermark_width, $watermark_height);
//                 }
//             }
//             $content_offset_y = $this->document_properties->margin_top;
//             $page_number = $this->context->get_page_number();
//             if ($this->document_properties->header_display == BandDisplay::always() || ($this->document_properties->header_display == BandDisplay::not_on_first_page() && $page_number != 1)) {
//                 $content_offset_y += $this->document_properties->header_size;
//                 $this->header_band->prepare($this->context, $this->pdf_doc);
//                 $this->header_band->create_render_elements($this->document_properties->header_size, $this->context, $this->pdf_doc);
//                 $this->header_band->render_pdf($this->document_properties->margin_left, $this->document_properties->margin_top, $this->pdf_doc);
//             }
//             if ($this->document_properties->footer_display == BandDisplay::always() || ($this->document_properties->footer_display == BandDisplay::not_on_first_page() && $page_number != 1)) {
//                 $this->footer_band->prepare($this->context, $this->pdf_doc);
//                 $this->footer_band->create_render_elements($this->document_properties->footer_size, $this->context, $this->pdf_doc);
//                 $this->footer_band->render_pdf($this->document_properties->margin_left, $footer_offset_y, $this->pdf_doc);
//             }

//             $this->content_band.render_pdf($this->document_properties->margin_left, $content_offset_y, $this->pdf_doc, true);
//         }
//         $this->header_band->cleanup();
//         $this->footer_band->cleanup();
        $dest = $this->filename ? 'F' : 'S';
        return $this->pdf_doc->output($this->filename, $dest);
    }
}

// class DocumentXLSXRenderer {
//     function __construct($header_band, $content_band, $footer_band, $report, $context, $filename) {
//         $this->header_band = $header_band;
//         $this->content_band = $content_band;
//         $this->footer_band = $footer_band;
//         $this->document_properties = $report->document_properties;
//         // $this->workbook_mem = BytesIO()
//         $this->workbook = $xlsxwriter->Workbook($filename ? $filename : $this->workbook_mem);
//         $this->worksheet = $this->workbook->add_worksheet();
//         $this->context = $context;
//         $this->filename = $filename;
//         $this->row = 0;
//         $this->column_widths = array();
//     }

//     function render() {
//         if ($this->document_properties->header_display != BandDisplay::never()) {
//             $this->render_band($this->header_band);
//         }
//         $this->render_band($this->content_band);
//         if ($this->document_properties->header_display != BandDisplay::never()) {
//             $this->render_band($this->footer_band);
//         }

//         foreach ($this->column_widths as $i => $column_width) {
//             if ($column_width > 0) {
//                 // setting the column width is just an approximation, in Excel the width
//                 // is the number of characters in the default font
//                 $this->worksheet->set_column($i, $i, $column_width / 7);
//             }
//         }

//         $this->workbook->close();
//         if (!$this->filename) {
//             // if no filename is given the spreadsheet data will be returned
//             $this->workbook_mem->seek(0);
//             return $this->workbook_mem->read();
//         }
//         return null;
//     }

//     function render_band($band) {
//         $band->prepare($this->context);
//         list($this->row, _) = $band->render_spreadsheet($this->row, 0, $this->context, $this);
//     }

//     function update_column_width($col, $width) {
//         if ($col >= count($this->column_widths)) {
//             // make sure column_width list contains entries for each column
//             // $this->column_widths->extend([-1] * (col + 1 - len($this->column_widths)));
//         }
//         if ($width > $this->column_widths[$col]) {
//             $this->column_widths[$col] = $width;
//         }
//     }

//     function write($row, $col, $colspan, $text, $cell_format, $width) {
//         if ($colspan > 1) {
//             $this->worksheet->merge_range($row, $col, $row, $col + $colspan - 1, $text, $cell_format);
//         } else {
//             $this->worksheet->write($row, $col, $text, $cell_format);
//             $this->update_column_width($col, $width);
//         }
//     }

//     function insert_image($row, $col, $image_filename, $width) {
//         $this->worksheet.insert_image($row, $col, $image_filename);
//         $this->update_column_width($col, $width);
//     }

//     function add_format($format_props) {
//         return $this->workbook->add_format($format_props);
//     }
// }

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

class FPDFRB extends FPDF {
    function __construct($document_properties, $additional_fonts) {
//         if ($document_properties->orientation == Orientation::portrait()) {
            $orientation = 'P';
//             $dimension = array($document_properties->page_width, $document_properties->page_height);
//         } else {
//             $orientation = 'L';
//             $dimension = array($document_properties->page_height, $document_properties->page_width);
//         }
        parent::__construct($orientation, 'pt', 'A4');
//         $this->x = 0;
//         $this->y = 0;
//         $this->set_doc_option('core_fonts_encoding', 'windows-1252');
//         $this->loaded_images = array();
//         // $this->available_fonts = array(
//         //     courier=dict(standard_font=true),
//         //     helvetica=dict(standard_font=true),
//         //     times=dict(standard_font=true));
//         // if additional_fonts:
//         //     for additional_font in additional_fonts:
//         //         filename = additional_font.get('filename', '')
//         //         style_map = {'': '', 'B': 'B', 'I': 'I', 'BI': 'BI'}
//         //         font = dict(standard_font=false, added=false, regular_filename=filename,
//         //                 bold_filename=additional_font.get('bold_filename', filename),
//         //                 italic_filename=additional_font.get('italic_filename', filename),
//         //                 bold_italic_filename=additional_font.get('bold_italic_filename', filename),
//         //                 style_map=style_map, uni=additional_font.get('uni', true))
//         //         // map styles in case there are no separate font-files for bold, italic or bold italic
//         //         // to avoid adding the same font multiple times to the pdf document
//         //         if font['bold_filename'] == font['regular_filename']:
//         //             style_map['B'] = ''
//         //         if font['italic_filename'] == font['bold_filename']:
//         //             style_map['I'] = style_map['B']
//         //         else if font['italic_filename'] == font['regular_filename']:
//         //             style_map['I'] = ''
//         //         if font['bold_italic_filename'] == font['italic_filename']:
//         //             style_map['BI'] = style_map['I']
//         //         else if font['bold_italic_filename'] == font['bold_filename']:
//         //             style_map['BI'] = style_map['B']
//         //         else if font['bold_italic_filename'] == font['regular_filename']:
//         //             style_map['BI'] = ''
//         //         font['style2filename'] = {'': filename, 'B': font['bold_filename'],
//         //                 'I': font['italic_filename'], 'BI': font['bold_italic_filename']}
//         //         $this->available_fonts[additional_font.get('value', '')] = $font;
    }

//     function add_image($img, $image_key) {
//         $this->loaded_images[$image_key] = $img;
//     }

//     function get_image($image_key) {
//         return $this->loaded_images->{$image_key};
//     }

//     function set_font($family, $style = '', $size = 0, $underline = false) {
//         $font = $this->available_fonts->{$family};
//         if ($font) {
//             if (!$font['standard_font']) {
//                 if ($style) {
//                     // replace of 'U' is needed because it is set for underlined text
//                     // when called from FPDF->add_page
//                     $style = $font['style_map']->{str_replace($style, 'U', '')};
//                 }
//                 if (!$font['added']) {
//                     $filename = $font['style2filename']->{$style};
//                     $this->add_font($family, $style, $filename, $font['uni']);
//                     $font['added'] = true;
//                 }
//             }
//             if ($underline) {
//                 $style += 'U';
//             }
//             parent::set_font($family, $style, $size);
//         }
//     }
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
                $elem = new TextElement($doc_element);
            } else if ($element_type == DocElementType::line()) {
        //         $elem = new LineElement($doc_element);
            } else if ($element_type == DocElementType::image()) {
        //         $elem = new ImageElement($doc_element, null);
            } else if ($element_type == DocElementType::bar_code()) {
        //         $elem = new BarCodeElement($doc_element);
            } else if ($element_type == DocElementType::table()) {
        //         $elem = new TableElement($doc_element);
            } else if ($element_type == DocElementType::page_break()) {
        //         $elem = new PageBreakElement($doc_element, null);
            } else if ($element_type == DocElementType::frame()) {
        //         $elem = new FrameElement($doc_element, $this->containers);
            } else if ($element_type == DocElementType::section()) {
        //         $elem = new SectionElement($doc_element, $this->containers);
            }

        //     if ($elem && $container) {
        //         if ($container->is_visible()) {
        //             if ($elem->x < 0) {
        //                 // $this->errors->append(Error('errorMsgInvalidPosition', $elem->id, 'position'));
        //             } else if ($elem->x + $elem->width > $container->width) {
        //                 // $this->errors->append(Error('errorMsgInvalidSize', $elem->id, 'position'));
        //             }
        //             if ($elem->y < 0) {
        //                 // $this->errors->append(Error('errorMsgInvalidPosition', $elem->id, 'position'));
        //             } else if ($elem->y + $elem->height > $container->height) {
        //                 // $this->errors->append(Error('errorMsgInvalidSize', $elem->id, 'position'));
        //             }
        //         }
        //         $container->add($elem);
        //     }
        }

        $this->context = null; //new Context($this->parameters, $this->data);

        // $computed_parameters = array();
        // $this->process_data($this->data, $data, $parameter_list, $is_test_data, $computed_parameters, array());
        // try {
        //     if (!$this->errors) {
        //         $this->compute_parameters($computed_parameters, $this->data);
        //     }
        // } catch (Exception $err) {
        //     array_push($this->errors, $err);
        // }
    }

    function generate_pdf($filename = '', $add_watermark = false) {
        $renderer = new DocumentPDFRenderer($this->header, $this->content, $this->footer, $this, $this->context, $this->additional_fonts, $filename, $add_watermark);
        return $renderer->render();
    }

    // function generate_xlsx($filename = '') {
    //     $renderer = DocumentXLSXRenderer($this->header, $this->content, $this->footer, $this->context, $filename);
    //     return $renderer->render();
    // }

    // // goes through all elements in header, content and footer and throws a ReportBroError in case
    // // an element is invalid
    // function verify() {
    //     if ($this->document_properties->header_display != BandDisplay::never()) {
    //         $this->header->prepare($this->context, true);
    //     }
    //     $this->content->prepare($this->context, true);
    //     if ($this->document_properties->header_display != BandDisplay::never()) {
    //         $this->footer->prepare($this->context, true);
    //     }
    // }

    // function parse_parameter_value(parameter, parent_id, is_test_data, parameter_type, value):
    //     error_field = 'test_data' if is_test_data else 'type'
    //     if parameter_type == ParameterType.string:
    //         if value is not null:
    //             if not isinstance(value, basestring):
    //                 raise RuntimeError('value of parameter {name} must be str type (unicode for Python 2.7.x)'.
    //                         format(name=parameter.name))
    //         else if not parameter.nullable:
    //             value = ''

    //     else if parameter_type == ParameterType.number:
    //         if value:
    //             if isinstance(value, basestring):
    //                 value = value.replace(',', '.')
    //             try:
    //                 value = decimal.Decimal(str(value))
    //             except (decimal.InvalidOperation, TypeError):
    //                 if parent_id and is_test_data:
    //                     $this->errors.append(Error('errorMsgInvalidTestData', object_id=parent_id, field='test_data'))
    //                     $this->errors.append(Error('errorMsgInvalidNumber', object_id=parameter.id, field='type'))
    //                 else:
    //                     $this->errors.append(Error('errorMsgInvalidNumber',
    //                                              object_id=parameter.id, field=error_field, context=parameter.name))
    //         else if value is not null:
    //             if isinstance(value, (int, long, float)):
    //                 value = decimal.Decimal(0)
    //             else if is_test_data and isinstance(value, basestring):
    //                 value = null if parameter.nullable else decimal.Decimal(0)
    //             else if not isinstance(value, decimal.Decimal):
    //                 if parent_id and is_test_data:
    //                     $this->errors.append(Error('errorMsgInvalidTestData', object_id=parent_id, field='test_data'))
    //                     $this->errors.append(Error('errorMsgInvalidNumber', object_id=parameter.id, field='type'))
    //                 else:
    //                     $this->errors.append(Error('errorMsgInvalidNumber',
    //                                              object_id=parameter.id, field=error_field, context=parameter.name))
    //         else if not parameter.nullable:
    //             value = decimal.Decimal(0)

    //     else if parameter_type == ParameterType.boolean:
    //         if value is not null:
    //             value = boolval(value)->{if not parameter.nullabl}:;
    //             value = false

    //     else if parameter_type == ParameterType.date:
    //         if isinstance(value, basestring):
    //             if is_test_data and not value:
    //                 value = null if parameter.nullable else datetime.datetime.now()
    //             else:
    //                 try:
    //                     format = '%Y-%m-%d'
    //                     colon_count = value.count(':')
    //                     if colon_count == 1:
    //                         format = '%Y-%m-%d %H:%M'
    //                     else if colon_count == 2:
    //                         format = '%Y-%m-%d %H:%M:%S'
    //                     value = datetime.datetime.strptime(value, format)
    //                 except (ValueError, TypeError):
    //                     try:
    //                         value = parser.parse(value)
    //                     except (ValueError, TypeError):
    //                         if parent_id and is_test_data:
    //                             $this->errors.append(Error('errorMsgInvalidTestData', object_id=parent_id, field='test_data'))
    //                             $this->errors.append(Error('errorMsgInvalidDate', object_id=parameter.id, field='type'))
    //                         else:
    //                             $this->errors.append(Error('errorMsgInvalidDate',
    //                                                     object_id=parameter.id, field=error_field, context=parameter.name))
    //         else if isinstance(value, datetime.date):
    //             if not isinstance(value, datetime.datetime):
    //                 value = datetime.datetime(value.year, value.month, value.day)
    //         else if value is not null:
    //             if parent_id and is_test_data:
    //                 $this->errors.append(Error('errorMsgInvalidTestData', object_id=parent_id, field='test_data'))
    //                 $this->errors.append(Error('errorMsgInvalidDate', object_id=parameter.id, field='type'))
    //             else:
    //                 $this->errors.append(Error('errorMsgInvalidDate',
    //                                          object_id=parameter.id, field=error_field, context=parameter.name))
    //         else if not parameter.nullable:
    //             value = datetime.datetime.now()
    //     return value

    // function process_data(dest_data, src_data, parameters, is_test_data, computed_parameters, parents):
    //     field = 'test_data' if is_test_data else 'type'
    //     parent_id = parents[-1].id if parents else null
    //     for parameter in parameters:
    //         if parameter.is_internal:
    //             continue
    //         if regex_valid_identifier.match(parameter.name) is null:
    //             $this->errors.append(Error('errorMsgInvalidParameterName',
    //                                      object_id=parameter.id, field='name', info=parameter.name))
    //         parameter_type = parameter.type
    //         if parameter_type in (ParameterType.average, ParameterType.sum) or parameter.eval:
    //             if not parameter.expression:
    //                 $this->errors.append(Error('errorMsgMissingExpression',
    //                                          object_id=parameter.id, field='expression', context=parameter.name))
    //             else:
    //                 parent_names = array()
    //                 for parent in parents:
    //                     parent_names.append(parent.name)
    //                 computed_parameters.append(dict(parameter=parameter, parent_names=parent_names))
    //         else:
    //             value = src_data.get(parameter.name)
    //             if parameter_type in (ParameterType.string, ParameterType.number,
    //                                   ParameterType.boolean, ParameterType.date):
    //                 value = $this->parse_parameter_value(parameter, parent_id, is_test_data, parameter_type, value)
    //             else if not parents:
    //                 if parameter_type == ParameterType.array:
    //                     if isinstance(value, list):
    //                         parents.append(parameter)
    //                         parameter_list = list(parameter.fields.values())
    //                         // create new list which will be assigned to dest_data to keep src_data unmodified
    //                         dest_array = array()

    //                         for row in value:
    //                             dest_array_item = dict()
    //                             $this->process_data(
    //                                 dest_data=dest_array_item, src_data=row, parameters=parameter_list,
    //                                 is_test_data=is_test_data, computed_parameters=computed_parameters,
    //                                 parents=parents)
    //                             dest_array.append(dest_array_item)
    //                         parents = parents[:-1]
    //                         value = dest_array
    //                     else if value is null:
    //                         if not parameter.nullable:
    //                             value = array()
    //                     else:
    //                         $this->errors.append(Error('errorMsgInvalidArray',
    //                                                  object_id=parameter.id, field=field, context=parameter.name))
    //                 else if parameter_type == ParameterType.simple_array:
    //                     if isinstance(value, list):
    //                         list_values = array()
    //                         for list_value in value:
    //                             parsed_value = $this->parse_parameter_value(
    //                                 parameter, parent_id, is_test_data, parameter.array_item_type, list_value)
    //                             list_values.append(parsed_value)
    //                         value = list_values
    //                     else if value is null:
    //                         if not parameter.nullable:
    //                             value = array()
    //                     else:
    //                         $this->errors.append(Error('errorMsgInvalidArray',
    //                                                  object_id=parameter.id, field=field, context=parameter.name))
    //                 else if parameter_type == ParameterType.map:
    //                     if value is null and not parameter.nullable:
    //                         value = dict()
    //                     if isinstance(value, dict):
    //                         if isinstance(parameter.children, list):
    //                             parents.append(parameter)
    //                             // create new dict which will be assigned to dest_data to keep src_data unmodified
    //                             dest_map = dict()

    //                             $this->process_data(
    //                                 dest_data=dest_map, src_data=value, parameters=parameter.children,
    //                                 is_test_data=is_test_data, computed_parameters=computed_parameters,
    //                                 parents=parents)
    //                             parents = parents[:-1]
    //                             value = dest_map
    //                         else:
    //                             $this->errors.append(Error('errorMsgInvalidMap',
    //                                                      object_id=parameter.id, field='type', context=parameter.name))
    //                     else:
    //                         $this->errors.append(Error('errorMsgMissingData',
    //                                                  object_id=parameter.id, field='name', context=parameter.name))
    //             dest_data[parameter.name] = value

    // function compute_parameters($computed_parameters, $data) {
    //     foreach ($computed_parameters as $computed_parameter) {
    //         $parameter = $computed_parameter['parameter'];
    //         $value = null;
    //         if (in_array($parameter->type, array(ParameterType::average(), ParameterType::sum())) {
    //             $expr = Context::strip_parameter_name($parameter->expression);
    //             $pos = strpos($expr, '.');
    //             if ($pos == false) {
    //                 $this->errors.append(Error('errorMsgInvalidAvgSumExpression', object_id=parameter.id, field='expression', context=parameter.name));
    //             } else {
    //                 $parameter_name = expr[:pos];
    //                 $parameter_field = expr[pos+1:];
    //                 items = data.get(parameter_name)
    //                 if not isinstance(items, list):
    //                     $this->errors.append(Error('errorMsgInvalidAvgSumExpression', object_id=parameter.id, field='expression', context=parameter.name))
    //                 else:
    //                     total = decimal.Decimal(0)
    //                     for item in items:
    //                         item_value = item.get(parameter_field)
    //                         if item_value is null:
    //                             $this->errors.append(Error('errorMsgInvalidAvgSumExpression', object_id=parameter.id, field='expression', context=parameter.name))
    //                             break;
    //                         total += item_value
    //                     if parameter.type == ParameterType.average:
    //                         value = total / len(items)
    //                     else if parameter.type == ParameterType.sum:
    //                         value = total
    //             }
    //         } else {
    //             $value = $this->context->evaluate_expression($parameter->expression, $parameter->id, 'expression');
    //         }

    //         data_entry = data
    //         valid = true
    //         for parent_name in computed_parameter['parent_names']:
    //             data_entry = data_entry.get(parent_name)
    //             if not isinstance(data_entry, dict):
    //                 $this->errors.append(Error('errorMsgInvalidParameterData',
    //                         object_id=parameter.id, field='name', context=parameter.name))
    //                 valid = false
    //         if valid:
    //             data_entry[parameter.name] = value
    // }
}