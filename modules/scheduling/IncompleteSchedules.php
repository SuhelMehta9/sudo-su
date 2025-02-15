<?php

#**************************************************************************
#  openSIS is a free student information system for public and non-public 
#  colleges from Open Solutions for Education, Inc. web: www.os4ed.com
#
#  openSIS is  web-based, open source, and comes packed with features that 
#  include student demographic info, scheduling, grade book, attendance, 
#  report cards, eligibility, transcripts, parent portal, 
#  student portal and more.   
#
#  Visit the openSIS web site at http://www.opensis.com to learn more.
#  If you have question regarding this system or the license, please send 
#  an email to info@os4ed.com.
#
#  This program is released under the terms of the GNU General Public License as  
#  published by the Free Software Foundation, version 2 of the License. 
#  See license.txt.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#***************************************************************************************
include('../../RedirectModulesInc.php');
$QI = DBQuery("SELECT PERIOD_ID,TITLE FROM college_periods WHERE COLLEGE_ID='" . UserCollege() . "' AND SYEAR='" . UserSyear() . "' ORDER BY SORT_ORDER ");
$periods_RET = DBGet($QI);

DrawBC("Scheduling > " . ProgramTitle());
echo "<FORM action=Modules.php?modname=" . strip_tags(trim($_REQUEST[modname])) . " method=POST>";
DrawHeader($period_select);
echo '</FORM>';
if ($_REQUEST['search_modfunc'] == 'list') {

    $mp = GetAllMP('QTR', UserMP());

    if (!isset($mp))
        $mp = GetAllMP('SEM', UserMP());

    if (!isset($mp))
        $mp = GetAllMP('FY', UserMP());
    Widgets('course');
    Widgets('request');
    $extra['SELECT'] .= ',sp.PERIOD_ID';
    $extra['FROM'] .= ',college_periods sp,schedule ss,course_periods cp,course_period_var cpv';
    $extra['WHERE'] .= ' AND (\'' . DBDate() . '\' BETWEEN ss.START_DATE AND ss.END_DATE OR ss.END_DATE IS NULL) AND ss.COLLEGE_ID=ssm.COLLEGE_ID AND ss.MARKING_PERIOD_ID IN (' . $mp . ') AND ss.COLLEGE_ROLL_NO=ssm.COLLEGE_ROLL_NO AND ss.SYEAR=ssm.SYEAR AND ss.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID AND cp.COURSE_PERIOD_ID=cpv.COURSE_PERIOD_ID AND cpv.PERIOD_ID=sp.PERIOD_ID ';
    if (UserStudentID())
        $extra['WHERE'] .= ' AND s.COLLEGE_ROLL_NO=\'' . UserStudentID() . '\' ';
    $extra['group'] = array('COLLEGE_ROLL_NO', 'PERIOD_ID');

    $schedule_RET = GetStuList($extra);
}
unset($extra);
$extra['force_search'] = true;
$extra['new'] = true;
Widgets('course');
Widgets('request');
foreach ($periods_RET as $period) {
    $extra['SELECT'] .= ',NULL AS PERIOD_' . $period['PERIOD_ID'];
    $extra['columns_after']['PERIOD_' . $period['PERIOD_ID']] = $period['TITLE'];
    $extra['functions']['PERIOD_' . $period['PERIOD_ID']] = '_preparePeriods';
}
if (!$_REQUEST['search_modfunc'])
    Search('college_roll_no', $extra);
else {
    $singular = 'Student with an incomplete schedule';
    $plural = 'students with incomplete schedules';

    $students_RET = GetStuList($extra);
    $bad_students[0] = array();
    foreach ($students_RET as $student) {
        if (count($schedule_RET[$student['COLLEGE_ROLL_NO']]) != count($periods_RET))
            $bad_students[] = $student;
    }
    if (!is_array($extra['columns_after'])) {
        $extra['columns_after'] = array();
    }
    unset($bad_students[0]);

    $link['FULL_NAME']['link'] = "Modules.php?modname=scheduling/Schedule.php";
    $link['FULL_NAME']['variables'] = array('college_roll_no' => 'COLLEGE_ROLL_NO');
    echo '<div class="panel panel-default">';
    echo '<div class="table-responsive">';
    ListOutput($bad_students, array('FULL_NAME' => 'Student', 'COLLEGE_ROLL_NO' => 'College Roll No', 'GRADE_ID' => 'Grade') + $extra['columns_after'], $singular, $plural, $link);
    echo "</div>"; //.table-responsive
    echo "</div>"; //.panel.panel-default
}

function _preparePeriods($value, $name) {
    global $THIS_RET, $schedule_RET;

    $period_id = substr($name, 7);
    if (!$schedule_RET[$THIS_RET['COLLEGE_ROLL_NO']][$period_id])
        return '<IMG SRC=assets/x.gif>';
    else
        return '';
}

?>