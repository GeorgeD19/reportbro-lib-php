<?php 
require('../vendor/autoload.php');

use MyCLabs\Enum\Enum;

class Border extends Enum
{
    private const grid = 1;
    private const frame_row = 2;
    private const frame = 3;
    private const row = 4;
    private const none = 5;

    public static function string($key = '') {
        $options = [
            'grid'=>self::grid(),
            'frame_row'=>self::frame_row(),
            'frame'=>self::frame(),
            'row'=>self::row(),
            'none'=>self::none()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::none();
    }
}

class RenderElementType extends Enum
{
    private const none = 0;
    private const complete = 1;
    private const between = 2;
    private const first = 3;
    private const last = 4;

    public static function string($key = '') {
        $options = [
            'none'=>self::none(),
            'complete'=>self::complete(),
            'between'=>self::between(),
            'first'=>self::first(),
            'last'=>self::last()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::none();
    }
}

class DocElementType extends Enum
{
    private const text = 1;
    private const image = 2;
    private const line = 3;
    private const page_break = 4;
    private const table_band = 5;
    private const table = 6;
    private const table_text = 7;
    private const bar_code = 8;
    private const frame = 9;
    private const section = 10;
    private const section_band = 11;

    public static function string($key = '') {
        $options = [
            'text'=>self::text(),
            'image'=>self::image(),
            'line'=>self::line(),
            'page_break'=>self::page_break(),
            'table_band'=>self::table_band(),
            'table'=>self::table(),
            'table_text'=>self::table_text(),
            'bar_code'=>self::bar_code(),
            'frame'=>self::frame(),
            'section'=>self::section(),
            'section_band'=>self::section_band()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::text();
    }
}

class ParameterType extends Enum
{
    private const none = 0;
    private const string = 1;
    private const number = 2;
    private const boolean = 3;
    private const date = 4;
    private const array = 5;
    private const simple_array = 6;
    private const map = 7;
    private const sum = 8;
    private const average = 9;
    private const image = 10;

    public static function string($key = '') {
        $options = [
            'none'=>self::none(),
            'string'=>self::string(),
            'number'=>self::number(),
            'boolean'=>self::boolean(),
            'date'=>self::date(),
            'array'=>self::array(),
            'simple_array'=>self::simple_array(),
            'map'=>self::map(),
            'sum'=>self::sum(),
            'average'=>self::average(),
            'image'=>self::image()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::none();
    }
}

class BandType extends Enum
{
    private const header = 1;
    private const content = 2;
    private const footer = 3;

    public static function string($key = '') {
        $options = [
            'header'=>self::header(),
            'content'=>self::content(),
            'footer'=>self::footer()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::content();
    }
}

class PageFormat extends Enum
{
    private const a4 = 1;
    private const a5 = 2;
    private const letter = 3;
    private const user_defined = 4;

    public static function string($key = '') {
        $options = [
            'a4'=>self::a4(),
            'a5'=>self::a5(),
            'letter'=>self::letter(),
            'user_defined'=>self::user_defined()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::a4();
    }
}

class Unit extends Enum
{
    private const pt = 1;
    private const mm = 2;
    private const inch = 3;

    public static function string($key = '') {
        $options = [
            'pt'=>self::pt(),
            'mm'=>self::mm(),
            'inch'=>self::inch()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::pt();
    }
}

class Orientation extends Enum
{
    private const portrait = 1;
    private const landscape = 2;

    public static function string($key = '') {
        $options = [
            'portrait'=>self::portrait(),
            'landscape'=>self::landscape()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::portrait();
    }
}

class BandDisplay extends Enum
{
    private const never = 1;
    private const always = 2;
    private const not_on_first_page = 3;

    public static function string($key = '') {
        $options = [
            'never'=>self::never(),
            'always'=>self::always(),
            'not_on_first_page'=>self::not_on_first_page()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::never();
    }
}

class HorizontalAlignment extends Enum
{
    private const left = 1;
    private const center = 2;
    private const right = 3;
    private const justify = 4;

    public static function string($key = '') {
        $options = [
            'left'=>self::left(),
            'center'=>self::center(),
            'right'=>self::right(),
            'justify'=>self::justify()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::left();
    }
}

class VerticalAlignment extends Enum
{
    private const top = 1;
    private const middle = 2;
    private const bottom = 3;

    public static function string($key = '') {
        $options = [
            'top'=>self::top(),
            'middle'=>self::middle(),
            'bottom'=>self::bottom()
        ];
        return array_key_exists($key, $options) ? $options[$key] : self::top();
    }
}