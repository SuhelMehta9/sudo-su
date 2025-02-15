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
include 'modules/grades/ConfigInc.php';
$q_total = array();
$s_total = array();
$f_total = 0;

foreach ($_REQUEST['teacher_grade'] as $ktitle => $data) {
    $cp_id = explode('-', $ktitle);
    $_REQUEST['values'][$ktitle] = ($data == '' ? '' : $data);
}
foreach ($_REQUEST['values'] as $key => $val) {
    $k = explode('-', $key);
    if ($k[0] == 'Q') {

        $q_total[$k[1]] = $q_total[$k[1]] + $val;
    }
    if ($k[0] == 'SEM') {
        if (substr($k[1], 0, 1) != 'E')
            $s_total[] = $k[1];
    }
    if ($k[0] == 'FY') {
        $f_total = $f_total + $val;
    }
}

if (!empty($s_total)) {
    $sem_id = implode(',', $s_total);
    $sql = "select marking_period_id from marking_periods where marking_period_id in($sem_id) and mp_type='semester'";
    $qr_sem = DBGet(DBQuery($sql));
    foreach ($qr_sem as $ks => $vs) {
        $marking_sem_id[] = $vs['MARKING_PERIOD_ID'];
    }
    foreach ($_REQUEST['values'] as $key => $val) {
        $k = explode('-', $key);
        if ($k[0] == 'SEM') {
            if (in_array($k[1], $marking_sem_id))
                $marking_sem_val[$k[1]] = $marking_sem_val[$k[1]] + $val;
            else {

                if (substr($k[1], 0, 1) != 'E') {
                    $pr_qr = DBGet(DBQuery("select parent_id from marking_periods where marking_period_id='$k[1]'"));
                    $parent_mp_id = $pr_qr[1]['PARENT_ID'];
                    $marking_sem_val[$parent_mp_id] = $marking_sem_val[$parent_mp_id] + $val;
                }
                if (substr($k[1], 0, 1) == 'E' && in_array(substr($k[1], 1), $marking_sem_id)) {

                    $marking_sem_val[substr($k[1], 1)] = $marking_sem_val[substr($k[1], 1)] + $val;
                }
                if (substr($k[1], 0, 1) == 'E' && !in_array(substr($k[1], 1), $marking_sem_id)) {
                    $pr_qr = DBGet(DBQuery("select parent_id from marking_periods where marking_period_id='" . substr($k[1], 1) . "'"));
                    $parent_mp_id = $pr_qr[1]['PARENT_ID'];
                    $marking_sem_val[$parent_mp_id] = $marking_sem_val[$parent_mp_id] + $val;
                }
            }
        }
    }
}
$quarter1 = array();
foreach ($q_total as $k1 => $v1) {

    if (substr($k1, 0, 1) == 'E') {
        $quarter1[substr($k1, 1)] = $quarter1[substr($k1, 1)] + $v1;
    } else {
        $quarter1[$k1] = $quarter1[$k1] + $v1;
    }
}
$flag_quarter = 0;
$flag_sem = 0;
$sem_total = 0;
foreach ($marking_sem_val as $sem_key => $sem_val) {
    $sem_total+=$sem_val;
}
if ($sem_total % 100 != 0)
    $flag_sem = 1;
foreach ($quarter1 as $q_key => $q_val) {

    if ($q_val > 100) {
        $flag_quarter = 1;
        break;
    }
}
if ($_REQUEST['values']) {
    if ($flag_sem == 0 && $flag_quarter == 0 && ($f_total == 100 || $f_total == 0)) {
        DBQuery('DELETE FROM program_user_config WHERE USER_ID=\'' . User('STAFF_ID') . '\' AND college_id=\'' . UserCollege() . '\' AND value like "%_' . UserCoursePeriod() . '%" AND PROGRAM=\'Gradebook\'');
        foreach ($_REQUEST['values'] as $title => $value) {
            if ($value != '')
                $value = $value . "_" . UserCoursePeriod();
            DBQuery('INSERT INTO program_user_config (USER_ID,COLLEGE_ID,PROGRAM,TITLE,VALUE) values(\'' . User('STAFF_ID') . '\',\'' . UserCollege() . '\',\'Gradebook\',\'' . $title . '\',\'' . str_replace("\'", "''", str_replace('%', '', $value)) . '\')');
        }
        unset($_REQUEST['values']);
        unset($_SESSION['_REQUEST_vars']['values']);
    }
    else {
        echo '<div class="text-danger">Total must be 100%!</div>';
    }
}

$config_RET = DBGet(DBQuery('SELECT TITLE,VALUE FROM program_user_config WHERE USER_ID=\'' . User('STAFF_ID') . '\' AND college_id=\'' . UserCollege() . '\' AND PROGRAM=\'Gradebook\' AND value like "%_' . UserCoursePeriod() . '%"'), array(), array('TITLE'));
if (count($config_RET)) {
    foreach ($config_RET as $title => $value)
        if (substr($title, 0, 3) == 'SEM' || substr($title, 0, 2) == 'FY' || substr($title, 0, 1) == 'Q') {
            $value1 = explode("_", $value[1]['VALUE']);
            $programconfig[$title] = $value1[0];
        } else {
            $value1 = explode("_" . UserCoursePeriod(), $value[1]['VALUE']);
            if (count($value1) > 1)
                $programconfig[$title] = $value1[0];
            else
                $programconfig[$title] = $value[1]['VALUE'];
        }
}
if (UserCoursePeriod() != '')
    $grades = DBGet(DBQuery('SELECT cp.TITLE AS CP_TITLE,c.TITLE AS COURSE_TITLE,cp.COURSE_PERIOD_ID,rcg.TITLE,rcg.ID FROM report_card_grades rcg,course_periods cp,course_period_var cpv,courses c WHERE cp.COURSE_ID=c.COURSE_ID AND cp.COURSE_PERIOD_ID=cpv.COURSE_PERIOD_ID  AND cp.TEACHER_ID=\'' . User('STAFF_ID') . '\' AND cp.COLLEGE_ID=rcg.COLLEGE_ID AND cp.COURSE_PERIOD_ID=' . UserCoursePeriod() . ' AND cp.SYEAR=rcg.SYEAR AND cp.SYEAR=\'' . UserSyear() . '\' AND rcg.GRADE_SCALE_ID=cp.GRADE_SCALE_ID AND cp.GRADE_SCALE_ID IS NOT NULL AND DOES_BREAKOFF=\'Y\' GROUP BY cp.COURSE_PERIOD_ID,rcg.ID ORDER BY rcg.BREAK_OFF IS NOT NULL DESC,rcg.BREAK_OFF DESC,rcg.SORT_ORDER '), array(), array('COURSE_PERIOD_ID'));
echo "<FORM class=form-horizontal action=Modules.php?modname=" . strip_tags(trim($_REQUEST[modname])) . " method=POST>";
PopTable('header', 'Configuration');

echo '<fieldset>';
echo '<h5 class="text-primary">General</h5>';

echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<h6><b>Score Rounding</b></h6>';
echo '<div class="mb-20">';
echo '<label class="radio-inline"><INPUT type=radio name=values[ROUNDING] value=UP' . (($programconfig['ROUNDING'] == 'UP') ? ' CHECKED' : '') . '>Up</label>';
echo '<label class="radio-inline"><INPUT type=radio name=values[ROUNDING] value=DOWN' . (($programconfig['ROUNDING'] == 'DOWN') ? ' CHECKED' : '') . '>Down</label>';
echo '<label class="radio-inline"><INPUT type=radio name=values[ROUNDING] value=NORMAL' . (($programconfig['ROUNDING'] == 'NORMAL') ? ' CHECKED' : '') . '>Normal</label>';
echo '<label class="radio-inline"><INPUT type=radio name=values[ROUNDING] value=\'\'' . (($programconfig['ROUNDING'] == '') ? ' CHECKED' : '') . '>None</label>';
echo '</div>';

if (!$programconfig['ASSIGNMENT_SORTING'])
    $programconfig['ASSIGNMENT_SORTING'] = 'ASSIGNMENT_ID';

echo '<h6><b>Assignment Sorting</b></h6>';
echo '<div class="mb-20">';
echo '<label class="radio-inline"><input type=radio name=values[ASSIGNMENT_SORTING] value=ASSIGNMENT_ID' . (($programconfig['ASSIGNMENT_SORTING'] == 'ASSIGNMENT_ID') ? ' CHECKED' : '') . '>Newest First</label>';
echo '<label class="radio-inline"><INPUT type=radio name=values[ASSIGNMENT_SORTING] value=DUE_DATE' . (($programconfig['ASSIGNMENT_SORTING'] == 'DUE_DATE') ? ' CHECKED' : '') . '>Due Date</label>';
echo '<label class="radio-inline"><INPUT type=radio name=values[ASSIGNMENT_SORTING] value=ASSIGNED_DATE' . (($programconfig['ASSIGNMENT_SORTING'] == 'ASSIGNED_DATE') ? ' CHECKED' : '') . '>Assigned Date</label>';
echo '<label class="radio-inline"><INPUT type=radio name=values[ASSIGNMENT_SORTING] value=UNGRADED' . (($programconfig['ASSIGNMENT_SORTING'] == 'UNGRADED') ? ' CHECKED' : '') . '>Ungraded</label>';
echo '</div>';

echo '<div>';
echo '<label class="checkbox-inline"><INPUT type=checkbox name=values[WEIGHT] value=Y' . (($programconfig['WEIGHT'] == 'Y') ? ' CHECKED' : '') . '>Weight grades</label>';
echo '<label class="checkbox-inline"><INPUT type=checkbox name=values[DEFAULT_ASSIGNED] value=Y' . (($programconfig['DEFAULT_ASSIGNED'] == 'Y') ? ' CHECKED' : '') . '>Assigned Date defaults to today</label>';
echo '</div>';
echo '<div class="mb-20">';
echo '<label class="checkbox-inline"><INPUT type=checkbox name=values[DEFAULT_DUE] value=Y' . (($programconfig['DEFAULT_DUE'] == 'Y') ? ' CHECKED' : '') . '>Due Date defaults to today</label>';
echo '<label class="checkbox-inline"><INPUT type=checkbox name=values[ELIGIBILITY_CUMULITIVE] value=Y' . (($programconfig['ELIGIBILITY_CUMULITIVE'] == 'Y') ? ' CHECKED' : '') . '>Calulate Extracurricular using Cumulative Semester grades</label>';
echo '</div>';

echo '</div>'; //.col-md-6
echo '<div class="col-md-6">';

echo '<div class="form-group">';
echo '<div class="col-md-2"><INPUT class="form-control" type=text name=values[ANOMALOUS_MAX] value="' . ($programconfig['ANOMALOUS_MAX'] != '' ? $programconfig['ANOMALOUS_MAX'] : '100') . '" size=3 maxlength=3></div><label class="col-md-10 control-label">% Allowed maximum percent in Anomalous grades</label>';
echo '</div>'; //.form-group

echo '<div class="form-group">';
echo '<div class="col-md-2"><INPUT class="form-control" type=text name=values[LATENCY] value="' . round($programconfig['LATENCY']) . '" size=3 maxlength=3></div><label class="col-md-10 control-label">Days until ungraded assignment grade appears in Parent/Student gradebook views</label>';
echo '</div>'; //.form-group


if ($commentsA_select) {
    echo '<div class="form-group">';
    echo '<div class="col-md-2"><SELECT class="form-control" name=values[COMMENT_A]><OPTION value="">N/A';
    foreach ($commentsA_select as $key => $val)
        echo '<OPTION value="' . $key . '"' . ($key == $programconfig['COMMENT_A'] ? ' SELECTED' : '') . '>' . $val[0];
    echo '</SELECT></div><label class="col-md-10 control-label">Default comment code</label>';
    echo '</div>'; //.form-group
}
echo '</div>'; //.col-md-6
echo '</div>'; //.row

echo '</fieldset>';

if (count($grades) > 0) {
    echo '<fieldset>';
    echo '<legend><b>Score Breakoff Points</b></legend>';
    echo '<TABLE cellspacing=1><TR><TD>';
    foreach ($grades as $course_period_id => $cp_grades) {
        $table = '<TABLE>';
        $table .= '<TR><TD rowspan=2 align=right width=100>' . $cp_grades[1]['COURSE_TITLE'] . ' - ' . substr($cp_grades[1]['CP_TITLE'], 0, strrpos(str_replace(' - ', ' ^ ', $cp_grades[1]['CP_TITLE']), '^')) . '</TD>';
        foreach ($cp_grades as $grade)
            $table .= '<TD><B>' . $grade['TITLE'] . '</B></TD>';
        $table .= '</TR>';
        $table .= '<TR>';
        foreach ($cp_grades as $grade)
            $table .= '<TD><INPUT type=text name=values[' . $course_period_id . '-' . $grade['ID'] . '] value="' . $programconfig[$course_period_id . '-' . $grade['ID']] . '" size=3 maxlength=5></TD>';
        $table .= '</TR>';
        $table .= '</TABLE>';
        echo DrawRoundedRect($table);
        echo '</TD></TR><TR><TD>';
    }
    echo '</TD></TR></TABLE>';
    echo '</fieldset></TD>';
}

$quarters_dt = DBGet(DBQuery('SELECT TITLE,MARKING_PERIOD_ID,SEMESTER_ID,DOES_GRADES,DOES_EXAM FROM college_quarters WHERE SYEAR=\'' . UserSyear() . '\' AND COLLEGE_ID=\'' . UserCollege() . '\' ORDER BY SORT_ORDER'));
$quarters = DBGet(DBQuery('SELECT TITLE,MARKING_PERIOD_ID,SEMESTER_ID,DOES_GRADES,DOES_EXAM FROM college_quarters WHERE SYEAR=\'' . UserSyear() . '\' AND COLLEGE_ID=\'' . UserCollege() . '\' ORDER BY SORT_ORDER'), array(), array('SEMESTER_ID'));
if ($quarters)
    $semesters = DBGet(DBQuery('SELECT TITLE,MARKING_PERIOD_ID,DOES_GRADES,DOES_EXAM FROM college_semesters WHERE SYEAR=\'' . UserSyear() . '\' AND COLLEGE_ID=\'' . UserCollege() . '\' ORDER BY SORT_ORDER'));
else
    $semesters = DBGet(DBQuery('SELECT TITLE,MARKING_PERIOD_ID, DOES_GRADES, NULL  AS DOES_EXAM FROM college_semesters WHERE SYEAR=\'' . UserSyear() . '\' AND COLLEGE_ID=\'' . UserCollege() . '\' ORDER BY SORT_ORDER'));
if ($semesters)
    $year = DBGet(DBQuery('SELECT TITLE,MARKING_PERIOD_ID,DOES_GRADES,DOES_EXAM FROM college_years WHERE SYEAR=\'' . UserSyear() . '\' AND COLLEGE_ID=\'' . UserCollege() . '\' ORDER BY SORT_ORDER'));
else
    $year = DBGet(DBQuery('SELECT TITLE,MARKING_PERIOD_ID,NULL AS DOES_GRADES,NULL AS DOES_EXAM FROM college_years WHERE SYEAR=\'' . UserSyear() . '\' AND COLLEGE_ID=\'' . UserCollege() . '\' ORDER BY SORT_ORDER'));

echo '<fieldset>';
echo '<h5 class="text-primary">Final Grading Percentages</h5>';
echo '<div class="table-responsive">';

if ($quarters_dt) {

    foreach ($quarters_dt as $qtrs) {

        if ($qtrs['DOES_GRADES'] == 'Y') {

            $table = '<TABLE width=100% class="table table-bordered table-striped">';
            $table .= '<TR><TD rowspan=2 valign="middle" style="width: 150px;"><div style="width: 150px; white-space: nowrap;">' . $qtrs['TITLE'] . '</div></TD>';
            $table .= '<TD>' . $qtrs['TITLE'] . '</TD>';

            if ($qtrs['DOES_EXAM'] == 'Y') {
                $table .= '<TD>' . $qtrs['TITLE'] . ' Exam</TD>';
            }
            $table .= '</TR><TR>';
            $total = 0;

            $table .= '<TD><INPUT class="form-control" type=text name=values[Q-' . $qtrs['MARKING_PERIOD_ID'] . '] value="' . $programconfig['Q-' . $qtrs['MARKING_PERIOD_ID']] . '" class= "mp_per" size=3 maxlength=3 onkeydown="return numberOnly(event);"></TD>';
            $total += $programconfig['Q-' . $qtrs['MARKING_PERIOD_ID']];

            if ($qtrs['DOES_EXAM'] == 'Y') {
                $table .= '<TD><INPUT class="form-control" type=text name=values[Q-E' . $qtrs['MARKING_PERIOD_ID'] . '] value="' . $programconfig['Q-E' . $qtrs['MARKING_PERIOD_ID']] . '" class= "mp_per" size=3 maxlength=3 onkeydown="return numberOnly(event);"></TD>';
                $total += $programconfig['Q-E' . $qtrs['MARKING_PERIOD_ID']];
            }
            if ($total != 100)
                $table .= '<TD style="width: 150px;"><div class="text-danger" style="width: 150px; white-space: nowrap;">Total not 100%!</div></TD>';
            $table .= '</TR>';
            $table .= '</tbody></TABLE>';
            echo $table;
        }
    }
}


if ($quarters) {
    foreach ($semesters as $sem)
        if ($sem['DOES_GRADES'] == 'Y') {
            $table = '<TABLE class="table table-bordered table-striped">';
            $table .= '<TR><TD rowspan=2 valign=middle style="width: 150px;">' . $sem['TITLE'] . '</TD>';
            foreach ($quarters[$sem['MARKING_PERIOD_ID']] as $qtr)
                $table .= '<TD>' . $qtr['TITLE'] . '</TD>';
            if ($sem['DOES_EXAM'] == 'Y')
                $table .= '<TD>' . $sem['TITLE'] . ' Exam</TD>';
            $table .= '</TR><TR>';
            $total = 0;
            foreach ($quarters[$sem['MARKING_PERIOD_ID']] as $qtr) {
                $table .= '<TD><INPUT class="form-control" type=text name=values[SEM-' . $qtr['MARKING_PERIOD_ID'] . '] value="' . $programconfig['SEM-' . $qtr['MARKING_PERIOD_ID']] . '" size=3 maxlength=3></TD>';
                $total += $programconfig['SEM-' . $qtr['MARKING_PERIOD_ID']];
            }
            if ($sem['DOES_EXAM'] == 'Y') {
                $table .= '<TD><INPUT class="form-control" type=text name=values[SEM-E' . $sem['MARKING_PERIOD_ID'] . '] value="' . $programconfig['SEM-E' . $sem['MARKING_PERIOD_ID']] . '" size=3 maxlength=3></TD>';
                $total += $programconfig['SEM-E' . $sem['MARKING_PERIOD_ID']];
            }
            if ($total != 100)
                $table .= '<TD style="width: 150px;"><div class="text-danger">Total not 100%!</div></TD>';
            $table .= '</TR>';
            $table .= '</tbody></TABLE>';
            echo $table;
        }
}
if ($year[1]['DOES_GRADES'] == 'Y') {
    $table = '<TABLE class="table table-bordered table-striped">';
    $table .= '<TR><TD rowspan=2 valign=middle style="white-space:nowrap; width: 150px;">' . $year[1]['TITLE'] . '</TD>';
//    foreach ($semesters as $sem) {
//        foreach ($quarters[$sem['MARKING_PERIOD_ID']] as $qtr)
////            $table .= '<TD style="white-space:nowrap">' . $qtr['TITLE'] . '</TD>';
//        if ($sem['DOES_GRADES'] == 'Y')
//            $table .= '<TD style="white-space:nowrap">' . $sem['TITLE'] . '</TD>';
////        if ($sem['DOES_EXAM'] == 'Y')
////            $table .= '<TD style="white-space:nowrap">' . $sem['TITLE'] . ' Exam</TD>';
//    }
    if (!empty($semesters)) {
        foreach ($semesters as $sem) {
//		foreach($quarters[$sem['MARKING_PERIOD_ID']] as $qtr)
//			$table .= '<TD style="white-space:nowrap">'.$qtr['TITLE'].'</TD>';
            if ($sem['DOES_GRADES'] == 'Y')
                $table .= '<TD style="white-space:nowrap">' . $sem['TITLE'] . '</TD>';
//		if($sem['DOES_EXAM']=='Y')
//			$table .= '<TD style="white-space:nowrap">'.$sem['TITLE'].' Exam</TD>';
        }
    }
    else {
        $table .= '<TD style="white-space:nowrap">' . $year[1]['TITLE'] . '</TD>';
    }
    if ($year[1]['DOES_EXAM'] == 'Y')
        $table .= '<TD>' . $year[1]['TITLE'] . ' Exam</TD>';
    $table .= '</TR><TR>';
    $total = 0;
//    foreach ($semesters as $sem) {
//        foreach ($quarters[$sem['MARKING_PERIOD_ID']] as $qtr) {
//            $table .= '<TD><INPUT class="form-control" type=text name=values[FY-' . $qtr['MARKING_PERIOD_ID'] . '] value="' . $programconfig['FY-' . $qtr['MARKING_PERIOD_ID']] . '" size=3 maxlength=3></TD>';
//            $total += $programconfig['FY-' . $qtr['MARKING_PERIOD_ID']];
//        }
//        if ($sem['DOES_GRADES'] == 'Y') {
//            $table .= '<TD><INPUT class="form-control" type=text name=values[FY-' . $sem['MARKING_PERIOD_ID'] . '] value="' . $programconfig['FY-' . $sem['MARKING_PERIOD_ID']] . '" size=3 maxlength=3></TD>';
//            $total += $programconfig['FY-' . $sem['MARKING_PERIOD_ID']];
//        }
//        if ($sem['DOES_EXAM'] == 'Y') {
//            $table .= '<TD><INPUT class="form-control" type=text name=values[FY-E' . $sem['MARKING_PERIOD_ID'] . '] value="' . $programconfig['FY-E' . $sem['MARKING_PERIOD_ID']] . '" size=3 maxlength=3></TD>';
//            $total += $programconfig['FY-E' . $sem['MARKING_PERIOD_ID']];
//        }
//    }

    if (!empty($semesters)) {
        foreach ($semesters as $sem) {
            //		foreach($quarters[$sem['MARKING_PERIOD_ID']] as $qtr)
            //		{
            //			$table .= '<TD><INPUT type=text name=values[FY-'.$qtr['MARKING_PERIOD_ID'].'] value="'.$programconfig['FY-'.$qtr['MARKING_PERIOD_ID']].'" class= "mp_per" size=3 maxlength=3 onkeydown="return numberOnly(event);"></TD>';
            //			$total += $programconfig['FY-'.$qtr['MARKING_PERIOD_ID']];
            //		}
            if ($sem['DOES_GRADES'] == 'Y') {
                $table .= '<TD><INPUT type=text class="form-control" name=values[FY-' . $sem['MARKING_PERIOD_ID'] . '] value="' . $programconfig['FY-' . $sem['MARKING_PERIOD_ID']] . '" class= "mp_per" size=3 maxlength=3 onkeydown="return numberOnly(event);"></TD>';
                $total += $programconfig['FY-' . $sem['MARKING_PERIOD_ID']];
            }
            //		if($sem['DOES_EXAM']=='Y')
            //		{
            //			$table .= '<TD><INPUT type=text name=values[FY-E'.$sem['MARKING_PERIOD_ID'].'] value="'.$programconfig['FY-E'.$sem['MARKING_PERIOD_ID']].'" class= "mp_per" size=3 maxlength=3 onkeydown="return numberOnly(event);"></TD>';
            //			$total += $programconfig['FY-E'.$sem['MARKING_PERIOD_ID']];
            //		}
        }
    } else {
        $table .= '<TD><INPUT type=text class="form-control"  name=values[FY-' . $year[1]['MARKING_PERIOD_ID'] . '] value="' . $programconfig['FY-' . $year[1]['MARKING_PERIOD_ID']] . '" class= "mp_per" size=3 maxlength=3 onkeydown="return numberOnly(event);"></TD>';
        $total += $programconfig['FY-' . $year[1]['MARKING_PERIOD_ID']];
    }




    if ($year[1]['DOES_EXAM'] == 'Y') {
        $table .= '<TD><INPUT type=text name=values[FY-E' . $year[1]['MARKING_PERIOD_ID'] . '] value="' . $programconfig['FY-E' . $year[1]['MARKING_PERIOD_ID']] . '" size=3 maxlength=3></TD>';
        $total += $programconfig['FY-E' . $year[1]['MARKING_PERIOD_ID']];
    }
    if ($total != 100)
        $table .= '<TD style="white-space:nowrap; width: 150px;"><div class="text-danger">Total not 100%!</div></TD>';
    $table .= '</TR>';
    $table .= '</TABLE>';
    echo $table;
}

echo '</div></fieldset>';

echo '<br/><INPUT type=submit value=Save class="btn btn-primary">';
PopTable('footer');
echo '</FORM>';
?>
