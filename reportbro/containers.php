<?php 
// from typing import List
// from .elements import DocElementBase, PageBreakElement
// from .enums import BandType

class Container {
    function __construct($container_id, &$containers, $report) {
        $this->id = $container_id;
        $this->report = $report;
        $this->doc_elements = array();  # type: List[DocElementBase]
        $this->width = 0;
        $this->height = 0;
        $containers[$this->id] = $this;

        $this->allow_page_break = true;
        $this->container_offset_y = 0;
        $this->sorted_elements = array();  # type: List[DocElementBase]
        $this->render_elements = array();  # type: List[DocElementBase]
        $this->render_elements_created = false;
        $this->explicit_page_break = true;
        $this->page_y = 0;
        $this->first_element_offset_y = 0;
        $this->used_band_height = 0;
    }

    function add($doc_element) {
        array_push($this->doc_elements, $doc_element);
    }

    function is_visible() {
        return true;
    }

    function prepare($ctx, $pdf_doc = null, $only_verify = false) {
        $this->sorted_elements = array();
        foreach ($this->doc_elements as $key => $elem) {
            if ($pdf_doc || !$elem->spreadsheet_hide || $only_verify) {
                $elem->prepare($ctx, $pdf_doc, $only_verify);
                if (!$this->allow_page_break) {
                    // make sure element can be rendered multiple times (for header/footer)
                    $elem->first_render_element = true;
                    $elem->rendering_complete = false;
                    array_push($this->sorted_elements, $elem);
                }
            }
        }
        
        if ($pdf_doc) {
            $this->sorted_elements = usort($this->sorted_elements, function($a, $b) {return strcmp($a->y, $b->y);});
            // predecessors are only needed for rendering pdf document
            foreach ($this->sorted_elements as $i => $elem) {
                foreach (array($i-1, -1, -1) as $j) {
                    $elem2 = $this->sorted_elements[$j];
                    if ($elem2 instanceof PageBreakElement) {
                        // new page so all elements before are not direct predecessors
                        break;
                    }
                    if ($elem->is_predecessor($elem2)) {
                        $elem->add_predecessor($elem2);
                    }
                }
            }
        
            $this->render_elements = array();
            $this->used_band_height = 0;
            $this->first_element_offset_y = 0;
        } else {
            $this->sorted_elements = usort($this->sorted_elements, function($a, $b) {return strcmp($a->y, $b->x);});
        }
    }

    function clear_rendered_elements() {
        $this->render_elements = array();
        $this->used_band_height = 0;
    }

    function get_render_elements_bottom() {
        $bottom = 0;
        foreach ($this->render_elements as $render_element) {
            if ($render_element->render_bottom > $bottom) {
                $bottom = $render_element->render_bottom;
            }
        }
        return $bottom;
    }

    function create_render_elements($container_height, $ctx, $pdf_doc) {
        $i = 0;
        $new_page = false;
        $processed_elements = array();
        $completed_elements = array();

        $this->render_elements_created = false;
        $set_explicit_page_break = false;
        // while not new_page and i < len($this->sorted_elements):
        //     elem = $this->sorted_elements[i]
        //     if elem.has_uncompleted_predecessor(completed_elements):
        //         # a predecessor is not completed yet -> start new page
        //         new_page = true;
        //     else:
        //         elem_deleted = false;
        //         if isinstance(elem, PageBreakElement):
        //             if $this->allow_page_break:
        //                 del $this->sorted_elements[i]
        //                 elem_deleted = true;
        //                 new_page = true;
        //                 set_explicit_page_break = true;
        //                 $this->page_y = elem.y
        //             else:
        //                 $this->sorted_elements = []
        //                 return true;
        //         else:
        //             complete = false;
        //             if elem.predecessors:
        //                 # element is on same page as predecessor element(s) so offset is relative to predecessors
        //                 offset_y = elem.get_offset_y()
        //             else:
        //                 if $this->allow_page_break:
        //                     if elem.first_render_element and $this->explicit_page_break:
        //                         offset_y = elem.y - $this->page_y
        //                     else:
        //                         offset_y = 0
        //                 else:
        //                     offset_y = elem.y - $this->first_element_offset_y
        //                     if offset_y < 0:
        //                         offset_y = 0

        //             if elem.is_printed(ctx):
        //                 if offset_y >= container_height:
        //                     new_page = true;
        //                 if not new_page:
        //                     render_elem, complete = elem.get_next_render_element(
        //                         offset_y, container_height=container_height, ctx=ctx, pdf_doc=pdf_doc)
        //                     if render_elem:
        //                         if complete:
        //                             processed_elements.append(elem)
        //                         $this->render_elements.append(render_elem)
        //                         $this->render_elements_created = true;
        //                         if render_elem.render_bottom > $this->used_band_height:
        //                             $this->used_band_height = render_elem.render_bottom
        //             else:
        //                 processed_elements.append(elem)
        //                 elem.finish_empty_element(offset_y)
        //                 complete = true;
        //             if complete:
        //                 completed_elements[elem.id] = true;
        //                 del $this->sorted_elements[i]
        //                 elem_deleted = true;
        //         if not elem_deleted:
        //             i += 1

        # in case of manual page break the element on the next page is positioned relative
        # to page break position
        $this->explicit_page_break = $this->allow_page_break ? $set_explicit_page_break : true;

        if (count($this->sorted_elements) > 0) {
            if ($this->allow_page_break) {
                array_push($this->render_elements, new PageBreakElement($this->report, array("y"=>-1)));
            }
            foreach ($processed_elements as $processed_element) {
                # remove dependency to predecessors because successor element is either already added
                # to render_elements or on new page
                foreach ($processed_element as $successor) {
                    $successor->clear_predecessors();
                }
            }
        }
        return (count($this->sorted_elements) == 0);
    }

    function render_pdf($container_offset_x, $container_offset_y, $pdf_doc, $cleanup = false) {
        $counter = 0;
        foreach ($this->render_elements as $render_element) {
            $counter += 1;
            if ($render_element instanceof PageBreakElement) {
                break;
            }
            $render_element->render_pdf($container_offset_x, $container_offset_y, $pdf_doc);
            if ($cleanup) {
                $render_element->cleanup();
            }
        }
        // $this->render_elements = $this->render_elements[$counter:]
    }

    // function render_spreadsheet(row, col, ctx, renderer):
    //     max_col = col
    //     i = 0
    //     count = len($this->sorted_elements)
    //     while i < count:
    //         elem = $this->sorted_elements[i]
    //         if elem.is_printed(ctx):
    //             j = i + 1
    //             # render elements with same y-coordinate in same spreadsheet row
    //             row_elements = [elem]
    //             while j < count:
    //                 elem2 = $this->sorted_elements[j]
    //                 if elem2.y == elem.y:
    //                     if elem2.is_printed(ctx):
    //                         row_elements.append(elem2)
    //                 else:
    //                     break
    //                 j += 1
    //             i = j
    //             current_row = row
    //             current_col = col
    //             for row_element in row_elements:
    //                 tmp_row, current_col = row_element.render_spreadsheet(
    //                     current_row, current_col, ctx, renderer)
    //                 row = max(row, tmp_row)
    //                 if current_col > max_col:
    //                     max_col = current_col
    //         else:
    //             i += 1
    //     return row, max_col

    function is_finished() {
        return (count($this->render_elements) == 0);
    }

    function cleanup() {
        foreach ($this->doc_elements as $elem) {
            $elem->cleanup();
        }
    }
}

class Frame extends Container {
    function __construct($width, $height, $container_id, $containers, $report) {
        parent::__construct($container_id, $containers, $report);
        $this->width = $width;
        $this->height = $height;
        $this->allow_page_break = false;
    }
}


class ReportBand extends Container {
    function __construct($band, $container_id, $containers, $report) {
        parent::__construct($container_id, $containers, $report);
        $this->band = $band;
        $this->width = $report->document_properties->page_width - $report->document_properties->margin_left - $report->document_properties->margin_right;
        if ($band == BandType::content()) {
            $this->height = $report->document_properties->content_height;
        } else if ($band == BandType::header()) {
            $this->allow_page_break = false;
            $this->height = $report->document_properties->header_size;
        } else if ($band == BandType::footer()) {
            $this->allow_page_break = false;
            $this->height = $report->document_properties->footer_size;
        }
    }

    function is_visible() {
        if ($this->band == BandType::header()) {
            return $this->report->document_properties->header;
        } else if ($this->band == BandType::footer()) {
            return $this->report->document_properties->footer;
        }
        return true;
    }
}
