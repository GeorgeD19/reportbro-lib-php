<?php 

namespace Reportbro;

use MabeEnum\Enum;

class Border extends Enum
{
    const grid = 1;
    const frame_row = 2;
    const frame = 3;
    const row = 4;
    const none = 5;
}

class RenderElementType extends Enum
{
    const none = 0;
    const complete = 1;
    const between = 2;
    const first = 3;
    const last = 4;
}

class DocElementType extends Enum
{
    const text = 1;
    const image = 2;
    const line = 3;
    const page_break = 4;
    const table_band = 5;
    const table = 6;
    const table_text = 7;
    const bar_code = 8;
    const frame = 9;
    const section = 10;
    const section_band = 11;
}

class ParameterType extends Enum
{
    const none = 0;
    const string = 1;
    const number = 2;
    const boolean = 3;
    const date = 4;
    const _array = 5;
    const simple_array = 6;
    const map = 7;
    const sum = 8;
    const average = 9;
    const image = 10;
}

class BandType extends Enum
{
    const header = 1;
    const content = 2;
    const footer = 3;
}

class PageFormat extends Enum
{
    const a4 = 1;
    const a5 = 2;
    const letter = 3;
    const user_defined = 4;
}

class Unit extends Enum
{
    const pt = 1;
    const mm = 2;
    const inch = 3;
}

class Orientation extends Enum
{
    const portrait = 1;
    const landscape = 2;
}

class BandDisplay extends Enum
{
    const never = 1;
    const always = 2;
    const not_on_first_page = 3;
}

class HorizontalAlignment extends Enum
{
    const left = 1;
    const center = 2;
    const right = 3;
    const justify = 4;
}

class VerticalAlignment extends Enum
{
    const top = 1;
    const middle = 2;
    const bottom = 3;
}
