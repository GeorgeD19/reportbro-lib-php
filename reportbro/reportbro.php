<?php
#
# Copyright (C) 2020 George Dunlop
#
# This file is a port of the reportbro-lib python, a library to generate PDF and Excel reports.
# Demos can be found at https://www.reportbro.com
#
# Dual licensed under AGPLv3 and ReportBro commercial license:
# https://www.reportbro.com/license
#
# You should have received a copy of the GNU Affero General Public License
# along with this program. If not, see https://www.gnu.org/licenses/
#
# Details for ReportBro commercial license can be found at
# https://www.reportbro.com/license/agreement
#

require('enums.php');
require('structs.php');

var_dump(new TextStyle(json_decode('{"bold":true,"horizontalAlignment": "right"}')));
