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
if ($_REQUEST['modfunc'] == 'save') {
    if (count($_REQUEST['cp_arr'])) {
        $cp_list = '\'' . implode('\',\'', $_REQUEST['cp_arr']) . '\'';
        
        $extra['DATE'] = GetMP();
        if ($extra['DATE'] == 'Custom') {
            if (UserMP() != '') {
                $current_mp_date = DBGet(DBQuery('SELECT START_DATE FROM marking_periods WHERE MARKING_PERIOD_ID=' . UserMP()));
                $extra['DATE'] = $current_mp_date[1]['START_DATE'];
            } else {
                $extra['DATE'] = date('Y-m-d');
            }
        }
        // get the fy marking period id, there should be exactly one fy marking period
        $fy_id = DBGet(DBQuery('SELECT MARKING_PERIOD_ID FROM college_years WHERE SYEAR=\'' . UserSyear() . '\' AND COLLEGE_ID=\'' . UserCollege() . '\''));
        $fy_id = $fy_id[1]['MARKING_PERIOD_ID'];

        $course_periods_RET = DBGet(DBQuery('SELECT cp.TITLE,cp.COURSE_PERIOD_ID,cpv.PERIOD_ID,cp.MARKING_PERIOD_ID,cpv.DAYS,c.TITLE AS COURSE_TITLE,cp.TEACHER_ID,(SELECT CONCAT(Trim(LAST_NAME),\', \',FIRST_NAME) FROM staff WHERE STAFF_ID=cp.TEACHER_ID) AS TEACHER FROM course_periods cp,course_period_var cpv,courses c WHERE c.COURSE_ID=cp.COURSE_ID AND cp.COURSE_PERIOD_ID=cpv.COURSE_PERIOD_ID AND cp.COURSE_PERIOD_ID IN (' . $cp_list . ') GROUP BY cpv.PERIOD_ID,cpv.COURSE_PERIOD_ID ORDER BY TEACHER'));

        $first_extra = $extra;
        $handle = PDFStart();
        $PCL_UserCoursePeriod = $_SESSION['UserCoursePeriod']; // save/restore for teachers
        foreach ($course_periods_RET as $teacher_id => $course_period) {
            unset($_openSIS['DrawHeader']);


            $_openSIS['User'] = array(1 => array('STAFF_ID' => $course_period['TEACHER_ID'], 'NAME' => 'name', 'PROFILE' => 'teacher', 'COLLEGES' => ',' . UserCollege() . ',', 'SYEAR' => UserSyear()));
            $_SESSION['UserCoursePeriod'] = $course_period['COURSE_PERIOD_ID'];

            echo '<table width="100%" bgcolor="#fff" cellpadding="0" cellspacing="0" border="0"><tbody>';
            echo '<tr><td>';

            echo "<table width=100%  style=\" font-family:Arial; font-size:12px;\" >";
            echo "<tr><td width=105>" . DrawLogo() . "</td>";
            echo "<td  style=\"font-size:15px; font-weight:bold; padding-top:20px;\">" . GetCollege(UserCollege()) . "<div style=\"font-size:12px;\">Teacher Class List</div></td>";
            echo "<td align=right style=\"padding-top:20px;\">" . ProperDate(DBDate()) . "<br />Powered by openSIS</td></tr>";
            echo "<tr><td colspan=3 style=\"border-top:1px solid #333;\">&nbsp;</td></tr>";
            echo "</table>";
            echo '</td></tr>';
            echo '<tr><td width="100%">';
            echo "<table>";
            echo '<tr><td>Teacher Name:</td>';
            echo '<td>' . $course_period['TEACHER'] . '</td></tr>';
            echo '<tr><td>Course Name:</td>';
            echo '<td>' . $course_period['COURSE_TITLE'] . '</td></tr>';
            echo '<tr><td>Course Period Name:</td>';
            echo '<td>' . GetActualCpName($course_period) . '</td></tr>';
            echo '<tr><td>Course Period Occurance:</td>';
            echo '<td>' . GetPeriodOcc($course_period['COURSE_PERIOD_ID']) . '</td></tr>';
            echo '<tr><td>Marking Period:</td>';
            echo '<td>' . GetMP($course_period['MARKING_PERIOD_ID']) . '</td></tr>';
            echo '</table>';
            echo '</td></tr>';
            $extra = $first_extra;
            $extra['MP'] = $course_period['MARKING_PERIOD_ID'];
            unset($extra['DATE']);
            $extra['search'] .= '<TR><TD align=center colspan=2><TABLE><TR><TD><DIV id=fields_div></DIV></TD></TR></TABLE></TD></TR>';
            $extra['new'] = true;
            $_openSIS['CustomFields'] = true;

            if ($_REQUEST['fields']['PARENTS']) {
                $extra['SELECT'] .= ',ssm.COLLEGE_ROLL_NO AS PARENTS';
                $view_other_RET['ALL_CONTACTS'][1]['VALUE'] = 'Y';
                if ($_REQUEST['relation'] != '') {
                    $_openSIS['makeParents'] = $_REQUEST['relation'];
                    $extra['students_join_address'] .= ' AND EXISTS (SELECT \'\' FROM students_join_people sjp WHERE sjp.COLLEGE_ROLL_NO=sa.COLLEGE_ROLL_NO AND LOWER(sjp.RELATIONSHIP) LIKE \'' . strtolower($_REQUEST['relation']) . '%\') ';
                }
            }
            if ($_REQUEST['fields']['USERNAME']) {
                $extra['SELECT'] .= ',la.username AS USERNAME';
                $extra['FROM'].=' ,login_authentication la';
                $extra['WHERE'].=' AND la.user_id=s.college_roll_no AND la.profile_id=3';
            }
            $extra['SELECT'] .= ',ssm.NEXT_COLLEGE,ssm.CALENDAR_ID,ssm.SYEAR,ssm.SECTION_ID,s.*';
            if ($_REQUEST['fields']['FIRST_INIT'])
                $extra['SELECT'] .= ',substr(s.FIRST_NAME,1,1) AS FIRST_INIT';

            if (!$extra['functions'])
                $extra['functions'] = array('NEXT_COLLEGE' => '_makeNextCollege', 'CALENDAR_ID' => '_makeCalendar', 'COLLEGE_ID' => 'GetCollege', 'PARENTS' => 'makeParents', 'BIRTHDATE' => 'ProperDate', 'SECTION_ID' => '_makeSection');

            if ($_REQUEST['search_modfunc'] == 'list') {
                if (!$fields_list) {
                    $fields_list = array('FULL_NAME' => (Preferences('NAME') == 'Common' ? 'Last, Common' : 'Last, First M'), 'FIRST_NAME' => 'First', 'FIRST_INIT' => 'First Initial', 'LAST_NAME' => 'Last', 'MIDDLE_NAME' => 'Middle', 'NAME_SUFFIX' => 'Suffix', 'COLLEGE_ROLL_NO' => 'College Roll No', 'GENDER' => 'Gender', 'GRADE_ID' => 'Grade', 'SECTION_ID' => 'Section', 'COLLEGE_ID' => 'College', 'NEXT_COLLEGE' => 'Rolling / Retention Options', 'CALENDAR_ID' => 'Calendar', 'USERNAME' => 'Username', 'PASSWORD' => 'Password', 'ALT_ID' => 'Alternate ID', 'BIRTHDATE' => 'DOB', 'EMAIL' => 'Email ID', 'ADDRESS' => 'Address', 'CITY' => 'City', 'STATE' => 'State', 'ZIPCODE' => 'Zip Code', 'PHONE' => 'Phone', 'MAIL_ADDRESS' => 'Mailing Address', 'MAIL_CITY' => 'Mailing City', 'MAIL_STATE' => 'Mailing State', 'MAIL_ZIPCODE' => 'Mailing Zipcode', 'PARENTS' => 'Contacts');
                    if ($extra['field_names'])
                        $fields_list += $extra['field_names'];

                    $periods_RET = DBGet(DBQuery('SELECT TITLE,PERIOD_ID FROM college_periods WHERE SYEAR=\'' . UserSyear() . '\' AND COLLEGE_ID=\'' . UserCollege() . '\' ORDER BY SORT_ORDER'));

                    foreach ($periods_RET as $period)
                        $fields_list['PERIOD_' . $period['PERIOD_ID']] = $period['TITLE'] . ' Teacher - Room';
                }

                $custom_RET = DBGet(DBQuery('SELECT TITLE,ID,TYPE FROM custom_fields WHERE SYSTEM_FIELD !=\'Y\' ORDER BY SORT_ORDER'));

                foreach ($custom_RET as $field) {

                    if (!$fields_list[$field['TITLE']]) {
                        $title = strtolower(trim($field['TITLE']));
                        if (strpos(trim($field['TITLE']), ' ') != 0) {
                            $p1 = substr(trim($field['TITLE']), 0, strpos(trim($field['TITLE']), ' '));
                            $p2 = substr(trim($field['TITLE']), strpos(trim($field['TITLE']), ' ') + 1);
                            $title = strtolower($p1 . '_' . $p2);
                        }
                        $fields_list[$title] = $field['TITLE'];
                        $extra['SELECT'] .= ',REPLACE(s.CUSTOM_' . $field['ID'] . ',"||",",") AS CUSTOM_' . $field['ID'];
                    }
                }


                foreach ($periods_RET as $period) {
                    if ($_REQUEST['month_include_active_date'])
                        $date = $_REQUEST['day_include_active_date'] . '-' . $_REQUEST['month_include_active_date'] . '-' . $_REQUEST['year_include_active_date'];
                    else
                        $date = DBDate();

                    if ($_REQUEST['fields']['PERIOD_' . $period['PERIOD_ID']] == 'Y')
                        $extra['SELECT'] .= ',(SELECT GROUP_CONCAT(DISTINCT CONCAT(COALESCE(st.FIRST_NAME,\' \'),\' \',COALESCE(st.LAST_NAME,\' \'),\' - \',COALESCE(r.TITLE,\' \'))) FROM staff st,schedule ss,course_periods cp,course_period_var cpv,rooms r WHERE ss.COLLEGE_ROLL_NO=ssm.COLLEGE_ROLL_NO AND cp.COURSE_PERIOD_ID=cpv.COURSE_PERIOD_ID AND r.ROOM_ID=cpv.ROOM_ID AND cp.COURSE_PERIOD_ID=ss.COURSE_PERIOD_ID AND cp.TEACHER_ID=st.STAFF_ID AND cpv.PERIOD_ID=\'' . $period['PERIOD_ID'] . '\' AND (\'' . $date . '\' BETWEEN ss.START_DATE AND ss.END_DATE OR \'' . $date . '\'>=ss.START_DATE AND ss.END_DATE IS NULL) LIMIT 1) AS PERIOD_' . $period['PERIOD_ID'];
                }

                if ($openSISModules['Food_Service'] && ($_REQUEST['fields']['FS_ACCOUNT_ID'] == 'Y' || $_REQUEST['fields']['FS_DISCOUNT'] == 'Y' || $_REQUEST['fields']['FS_STATUS'] == 'Y' || $_REQUEST['fields']['FS_BARCODE'] == 'Y' || $_REQUEST['fields']['FS_BALANCE'] == 'Y')) {
                    $extra['FROM'] = ',FOOD_SERVICE_STUDENT_ACCOUNTS fssa';
                    $extra['WHERE'] = ' AND fssa.COLLEGE_ROLL_NO=ssm.COLLEGE_ROLL_NO';
                    if ($_REQUEST['fields']['FS_ACCOUNT_ID'] == 'Y')
                        $extra['SELECT'] .= ',fssa.ACCOUNT_ID AS FS_ACCOUNT_ID';
                    if ($_REQUEST['fields']['FS_DISCOUNT'] == 'Y')
                        $extra['SELECT'] .= ',coalesce(fssa.DISCOUNT,\'Full\') AS FS_DISCOUNT';
                    if ($_REQUEST['fields']['FS_STATUS'] == 'Y')
                        $extra['SELECT'] .= ',coalesce(fssa.STATUS,\'Active\') AS FS_STATUS';
                    if ($_REQUEST['fields']['FS_BARCODE'] == 'Y')
                        $extra['SELECT'] .= ',fssa.BARCODE AS FS_BARCODE';
                    if ($_REQUEST['fields']['FS_BALANCE'] == 'Y')
                        $extra['SELECT'] .= ',(SELECT fsa.BALANCE FROM FOOD_SERVICE_ACCOUNTS fsa WHERE fsa.ACCOUNT_ID=fssa.ACCOUNT_ID) AS FS_BALANCE';
                    $fields_list += array('FS_ACCOUNT_ID' => 'F/S Account ID', 'FS_DISCOUNT' => 'F/S Discount', 'FS_STATUS' => 'F/S Status', 'FS_BARCODE' => 'F/S Barcode', 'FS_BALANCE' => 'F/S Balance',);
                }

                if ($_REQUEST['fields']) {
                    foreach ($_REQUEST['fields'] as $field => $on) {
                        $columns[strtoupper($field)] = $fields_list[$field];
                        if (!$fields_list[$field]) {
                            $get_column = DBGet(DBQuery('SELECT ID,TITLE FROM custom_fields  ORDER BY SORT_ORDER'));
                            foreach ($get_column as $COLUMN_NAME) {
                                if ('CUSTOM_' . $COLUMN_NAME['ID'] == $field)
                                    $columns[strtoupper($field)] = $COLUMN_NAME['TITLE'];
                                else if (str_replace(" ", "_", strtoupper($COLUMN_NAME['TITLE'])) == strtoupper($field))
                                    $columns[strtoupper($field)] = $COLUMN_NAME['TITLE'];
                            }
                            if (strpos($field, 'CUSTOM') === 0) {
                                $custom_id = str_replace("CUSTOM_", "", $field);
                                $custom_RET = DBGet(DBQuery('SELECT TYPE FROM custom_fields WHERE ID=' . $custom_id));
                                if ($custom_RET[1]['TYPE'] == 'date' && !$extra['functions'][$field]) {
                                    $extra['functions'][$field] = 'ProperDate';
                                } elseif ($custom_RET[1]['TYPE'] == 'codeds' && !$extra['functions'][$field]) {
                                    $extra['functions'][$field] = 'DeCodeds';
                                }
                            }
                        }
                    }

                    $RET = GetStuList($extra);
                    
                    $list_attr = DBGet(DBQuery("SHOW COLUMNS FROM `students` "));
                    foreach ($list_attr as $data) {

                        $list_attr_val[] = strtoupper($data['FIELD']);
                        
                    }


                    foreach ($columns as $stu_indx => $stu_data) {
                        $f = 0;

                        if (!in_array($stu_indx, $list_attr_val)) {
                            $f = 1;
                        } else {
                            $f = 0;
                            break;
                        }
                    }

                    if ($_REQUEST['ADDRESS_ID'] || $_REQUEST['fields']['ADDRESS'] || $_REQUEST['fields']['CITY'] || $_REQUEST['fields']['STATE'] || $_REQUEST['fields']['ZIPCODE'] || $_REQUEST['fields']['PHONE'] || $_REQUEST['fields']['MAIL_ADDRESS'] || $_REQUEST['fields']['MAIL_CITY'] || $_REQUEST['fields']['MAIL_STATE'] || $_REQUEST['fields']['MAIL_ZIPCODE'] || $_REQUEST['fields']['PARENTS']) {


                        foreach ($RET as $stu_key => $stu_val) {

                            $add_reslt = "SELECT sa.STREET_ADDRESS_1 AS ADDRESS,sa.CITY,sa.STATE,sa.ZIPCODE,COALESCE((SELECT STREET_ADDRESS_1 FROM student_address WHERE college_roll_no=" . $stu_val['COLLEGE_ROLL_NO'] . " AND TYPE='MAIL'),sa.STREET_ADDRESS_1) AS 

                                        MAIL_ADDRESS,COALESCE((SELECT CITY FROM student_address WHERE college_roll_no=" . $stu_val['COLLEGE_ROLL_NO'] . " AND TYPE='MAIL'),sa.CITY) AS MAIL_CITY,COALESCE((SELECT STATE FROM student_address WHERE college_roll_no=" . $stu_val['COLLEGE_ROLL_NO'] . " AND TYPE='MAIL'),sa.STATE) AS MAIL_STATE,

                                        COALESCE((SELECT ZIPCODE FROM student_address WHERE college_roll_no=" . $stu_val['COLLEGE_ROLL_NO'] . " AND TYPE='MAIL'),sa.ZIPCODE) AS MAIL_ZIPCODE  from student_address sa   WHERE  sa.TYPE='HOME ADDRESS' AND sa.COLLEGE_ROLL_NO=" . $stu_val['COLLEGE_ROLL_NO'];

                            $res = DBGet(DBQuery($add_reslt));

                            foreach ($res[1] as $add_key => $add_val) {
                                $RET[$stu_key][$add_key] = $add_val;
                            }

                            if (empty($res[1]) && $f == 1)
                                unset($RET[$stu_key]);
                        }
                    }

                    if ($extra['array_function'] && function_exists($extra['array_function']))
                        $extra['array_function']($RET);

                    if (count($_REQUEST['cp_arr']) > 0)
                        $cr_pr_id = implode(",", $_REQUEST['cp_arr']);
                    else {
                        $cr_pr_id = 0;
                    }
                    $date = DBDate();

                    $get_schedule = DBGet(DBQuery('SELECT count(ss.college_roll_no) AS TOT FROM students s,course_periods cp,schedule ss ,student_enrollment ssm WHERE ssm.COLLEGE_ROLL_NO=s.COLLEGE_ROLL_NO AND ssm.COLLEGE_ROLL_NO=ss.COLLEGE_ROLL_NO AND ssm.COLLEGE_ID=' . UserCollege() . ' AND ssm.SYEAR=' . UserSyear() . ' AND ssm.SYEAR=cp.SYEAR AND ssm.SYEAR=ss.SYEAR AND (ss.END_DATE>=\'' . $date . '\' OR ss.END_DATE IS NULL) AND cp.COURSE_PERIOD_ID IN (' . $cr_pr_id . ') AND cp.COURSE_ID=ss.COURSE_ID AND cp.COURSE_PERIOD_ID=ss.COURSE_PERIOD_ID AND (\'' . $date . '\'<=ssm.END_DATE OR ssm.END_DATE IS NULL) AND (\'' . $date . '\'<=ss.END_DATE OR ss.END_DATE IS NULL)'));

                    if ($get_schedule[1]['TOT'] > 0 && count($RET) > 0)
                        $table = ListOutputPrintReportMod($RET, $columns);
                    else
                        $table = '<br><br><b><font style="color:red">No students found.</font></b>';
                    unset($cr_pr_id);
                    unset($date);
                }
            }


            echo '<tr><td width="100%">';
            echo $table;
            echo '</td></tr></tbody></table>';
            echo '<br><br>';
            echo "<div style=\"page-break-before: always;\"></div>";
        }
        $_SESSION['UserCoursePeriod'] = $PCL_UserCoursePeriod;
        PDFStop($handle);
    }
    else {
        BackPrompt('You must choose at least one course period.');
    }
}

if (!$_REQUEST['modfunc']) {
    DrawBC("Scheduling > " . ProgramTitle());

    if (User('PROFILE') != 'admin') {
        $_REQUEST['search_modfunc'] = 'list';
    }
    if ($_REQUEST['search_modfunc'] == 'list' || $_REQUEST['search_modfunc'] == 'select') {
        $_REQUEST['search_modfunc'] = 'select';

        $extra['extra_header_left'] .= '<div class="form-group"><div class="checkbox checkbox-switch switch-success switch-xs"><label><INPUT type=checkbox name=include_inactive value=Y><span></span>Include Inactive Students</label></div></div>';

        $Search = 'mySearch';
        include('modules/miscellaneous/Export.php');
    } else {
        echo "<FORM class=\"form-horizontal\" action=Modules.php?modname=" . strip_tags(trim($_REQUEST[modname])) . "&modfunc=" . strip_tags(trim($_REQUEST[modfunc])) . "&search_modfunc=list&next_modname=" . strip_tags(trim($_REQUEST[next_modname])) . " method=POST>";


        PopTable('header', 'Search');

        $RET = DBGet(DBQuery('SELECT s.STAFF_ID,CONCAT(Trim(s.LAST_NAME),\', \',s.FIRST_NAME) AS FULL_NAME FROM staff s,staff_college_relationship ssr WHERE s.STAFF_ID=ssr.STAFF_ID AND s.PROFILE=\'' . 'teacher' . '\' AND FIND_IN_SET(\'' . UserCollege() . '\', ssr.COLLEGE_ID)>0 AND ssr.SYEAR=\'' . UserSyear() . '\' ORDER BY FULL_NAME'));

        echo '<div class="row">';
        echo '<div class="col-lg-6">';
        echo '<div class="form-group"><label class="control-label col-lg-4">Teacher</label><div class="col-lg-8">';
        echo "<SELECT name=teacher_id class=form-control><OPTION value=''>N/A</OPTION>";
        foreach ($RET as $teacher)
            echo "<OPTION value=$teacher[STAFF_ID]>$teacher[FULL_NAME]</OPTION>";
        echo '</SELECT>';
        echo '</div></div>';
        echo '</div>'; //.col-lg-6

        $RET = DBGet(DBQuery("SELECT SUBJECT_ID,TITLE FROM course_subjects WHERE COLLEGE_ID='" . UserCollege() . "' AND SYEAR='" . UserSyear() . "' ORDER BY TITLE"));
        echo '<div class="col-lg-6">';
        echo '<div class="form-group"><label class="control-label col-lg-4">Subject</label><div class="col-lg-8">';
        echo "<SELECT name=subject_id class=form-control><OPTION value=''>N/A</OPTION>";
        foreach ($RET as $subject)
            echo "<OPTION value=$subject[SUBJECT_ID]>$subject[TITLE]</OPTION>";
        echo '</SELECT></div></div>';
        echo '</div>'; //.col-lg-6
        echo '</div>'; //.row

        $RET = DBGet(DBQuery("SELECT PERIOD_ID,TITLE FROM college_periods WHERE SYEAR='" . UserSyear() . "' AND COLLEGE_ID='" . UserCollege() . "' ORDER BY SORT_ORDER"));
        echo '<div class="row">';
        echo '<div class="col-lg-6">';
        echo '<div class="form-group"><label class="control-label col-lg-4">Period</label><div class="col-lg-8">';
        echo "<SELECT name=period_id class=form-control><OPTION value=''>N/A</OPTION>";
        foreach ($RET as $period)
            echo "<OPTION value=$period[PERIOD_ID]>$period[TITLE]</OPTION>";
        echo '</SELECT></div></div>';
        echo '</div>'; //.col-lg-6

        echo '<div class="col-lg-6">';
        Widgets('course');
        echo $extra['search'];
        echo '</div>'; //.col-lg-6
        echo '</div>'; //.row

        echo '<div>';
        echo Buttons('Submit', 'Reset');
        echo '</div>';
        PopTable('footer');
        echo '</FORM>';
    }
}

/*
 * Modal Start
 */
$modal_flag=1;
if($_REQUEST['modname']=='scheduling/PrintClassLists.php' && $_REQUEST['modfunc']=='save')
$modal_flag=0;
if($modal_flag==1)
{
echo '<div id="modal_default" class="modal fade">';
echo '<div class="modal-dialog modal-lg">';
echo '<div class="modal-content">';

echo '<div class="modal-header">';
echo '<button type="button" class="close" data-dismiss="modal">×</button>';
echo '<h5 class="modal-title">Choose course</h5>';
echo '</div>'; //.modal-header

echo '<div class="modal-body">';
echo '<div id="conf_div" class="text-center"></div>';
echo '<div class="row" id="resp_table">';
echo '<div class="col-md-4">';
$sql = "SELECT SUBJECT_ID,TITLE FROM course_subjects WHERE COLLEGE_ID='" . UserCollege() . "' AND SYEAR='" . UserSyear() . "' ORDER BY TITLE";
$QI = DBQuery($sql);
$subjects_RET = DBGet($QI);

echo '<h6>' . count($subjects_RET) . ((count($subjects_RET) == 1) ? ' Subject was' : ' Subjects were') . ' found.</h6>';
if (count($subjects_RET) > 0) {
    echo '<table class="table table-bordered"><thead><tr class="alpha-grey"><th>Subject</th></tr></thead>';
    echo '<tbody>';
    foreach ($subjects_RET as $val) {
        echo '<tr><td><a href=javascript:void(0); onclick="MassDropModal(' . $val['SUBJECT_ID'] . ',\'courses\')">' . $val['TITLE'] . '</a></td></tr>';
    }
    echo '</tbody>';
    echo '</table>';
}
echo '</div>';
echo '<div class="col-md-4"><div id="course_modal"></div></div>';
echo '<div class="col-md-4"><div id="cp_modal"></div></div>';
echo '</div>'; //.row
echo '</div>'; //.modal-body

echo '</div>'; //.modal-content
echo '</div>'; //.modal-dialog
echo '</div>'; //.modal
}

function mySearch($extra) {

    echo "<FORM name=exp id=exp action=ForExport.php?modname=" . strip_tags(trim($_REQUEST[modname])) . "&head_html=Teacher+Class+List&modfunc=save&search_modfunc=list&_openSIS_PDF=true onsubmit=document.forms[0].relation.value=document.getElementById(\"relation\").value; method=POST target=_blank>";
    echo '<DIV id=fields_div></DIV>';
    DrawHeader('', $extra['header_right']);
    DrawHeader($extra['extra_header_left'], $extra['extra_header_right']);

    if (User('PROFILE') == 'admin') {
        if ($_REQUEST['teacher_id'])
            $where .= " AND cp.TEACHER_ID='$_REQUEST[teacher_id]'";
        if ($_REQUEST['first'])
            $where .= " AND UPPER(s.FIRST_NAME) LIKE '" . strtoupper($_REQUEST['first']) . "%'";
        if ($_REQUEST['w_course_period_id'] && $_REQUEST['w_course_period_id_which'] != 'course')
            $where .= " AND cp.COURSE_PERIOD_ID='" . $_REQUEST['w_course_period_id'] . "'";
        if ($_REQUEST['subject_id']) {
            $from .= ",courses c";
            $where .= " AND c.COURSE_ID=cp.COURSE_ID AND c.SUBJECT_ID='" . $_REQUEST['subject_id'] . "'";
        }
        if ($_REQUEST['period_id']) {
            $where .= " AND cpv.PERIOD_ID='" . $_REQUEST['period_id'] . "'";
        }
        $sql = "SELECT cp.COURSE_PERIOD_ID,cp.COURSE_PERIOD_ID as STU_COURSE_PERIOD_ID,cp.TITLE FROM course_periods cp,course_period_var cpv$from WHERE cp.COLLEGE_ID='" . UserCollege() . "' AND cp.COURSE_PERIOD_ID=cpv.COURSE_PERIOD_ID AND cp.SYEAR='" . UserSyear() . "'$where";
    } else { // teacher
        $sql = "SELECT cp.COURSE_PERIOD_ID,cp.COURSE_PERIOD_ID as STU_COURSE_PERIOD_ID,cp.TITLE FROM course_periods cp,course_period_var cpv WHERE cp.COLLEGE_ID='" . UserCollege() . "' AND cp.COURSE_PERIOD_ID=cpv.COURSE_PERIOD_ID AND cp.SYEAR='" . UserSyear() . "' AND cp.TEACHER_ID='" . User('STAFF_ID') . "'";
    }
    $sql .= ' GROUP BY cp.COURSE_PERIOD_ID ORDER BY (SELECT SORT_ORDER FROM college_periods WHERE PERIOD_ID=cpv.PERIOD_ID)';
    $schedule_stu = DBGet(DBQuery($sql));
    foreach ($schedule_stu as $val) {
        $arr[] = $val['COURSE_PERIOD_ID'];
    }
    if (count($arr) > 0)
        $cr_pr_id = implode(",", $arr);
    else {
        $cr_pr_id = 0;
    }
    $date = DBDate();

    $stu_schedule_qr = DBGet(DBQuery('SELECT count(ss.college_roll_no) AS TOT FROM students s,course_periods cp,schedule ss ,student_enrollment ssm WHERE ssm.COLLEGE_ROLL_NO=s.COLLEGE_ROLL_NO AND ssm.COLLEGE_ROLL_NO=ss.COLLEGE_ROLL_NO AND ssm.COLLEGE_ID=' . UserCollege() . ' AND ssm.SYEAR=' . UserSyear() . ' AND ssm.SYEAR=cp.SYEAR AND ssm.SYEAR=ss.SYEAR AND (ss.END_DATE>=\'' . $date . '\' OR ss.END_DATE IS NULL) AND cp.COURSE_PERIOD_ID IN (' . $cr_pr_id . ') AND cp.COURSE_ID=ss.COURSE_ID AND cp.COURSE_PERIOD_ID=ss.COURSE_PERIOD_ID AND (\'' . $date . '\'<=ssm.END_DATE OR ssm.END_DATE IS NULL) AND (\'' . $date . '\'<=ss.END_DATE OR ss.END_DATE IS NULL)'));
     
    if ($stu_schedule_qr[1]['TOT'] > 0) {
        echo '<div class="alert bg-success alert-styled-left">' . ($stu_schedule_qr[1]['TOT'] == 1 ? $stu_schedule_qr[1]['TOT'] . "student is found." : $stu_schedule_qr[1]['TOT'] . " students are found.") . '</div>';
    } else {
        echo '<div class="alert bg-danger alert-styled-left">No student found.</div>';
    }
    $course_periods_RET = DBGet(DBQuery($sql), array('COURSE_PERIOD_ID' => '_makeChooseCheckbox', 'STU_COURSE_PERIOD_ID' => '_make_no_student'));
    $LO_columns = array('COURSE_PERIOD_ID' => '</A><INPUT type=checkbox value=Y name=controller checked onclick="checkAll(this.form,this.form.controller.checked,\'cp_arr\');"><A>', 'TITLE' => 'Course Period', 'STU_COURSE_PERIOD_ID' => 'No of schedule student');

    echo '<INPUT type=hidden name=relation>';

    echo '<div class="panel panel-default">';
    ListOutput($course_periods_RET, $LO_columns, 'Course Period', 'Course Periods', array(), array(), array('save' => true, 'count' => false, 'search' => true));
    echo '</div>';

    if (count($course_periods_RET) != 0)
        echo '<div class="text-right"><INPUT type=submit class="btn btn-primary" value=\'Create Class Lists for Selected Course Periods\'></div>';
    echo "</FORM>";
}

function _makeChooseCheckbox($value, $title) {
    return "<INPUT type=checkbox name=cp_arr[] value=$value checked>";
}

function GetActualCpName($cp_array) {
    $cp_name = $cp_array['TITLE'];
    $teacher_name = $cp_array['TEACHER_F'];
    $cp_name = explode('-', $cp_name);
    return $cp_name[0] . ' - ' . $cp_name[1];
}

function GetPeriodOcc($cp_id) {
    $period_name = array();
    $days = array('M' => 'Monday', 'T' => 'Tuesday', 'W' => 'Wednesday', 'H' => 'Thursday', 'F' => 'Friday', 'S' => 'Saturday', 'U' => 'Sunday');
    $get_det = DBGet(DBQuery('SELECT cpv.DAYS,cpv.START_TIME,cpv.END_TIME,sp.TITLE FROM course_period_var cpv,college_periods sp WHERE cpv.PERIOD_ID=sp.PERIOD_ID AND cpv.COURSE_PERIOD_ID=' . $cp_id . ' GROUP BY cpv.DAYS,sp.PERIOD_ID,cpv.COURSE_PERIOD_ID'));
    foreach ($get_det as $gd) {
        $period_name[] = $days[$gd['DAYS']] . ' - ' . $gd['TITLE'] . ' (' . date("g:i A", strtotime($gd['START_TIME'])) . ' - ' . date("g:i A", strtotime($gd['END_TIME'])) . ')';
    }
    return implode(',', $period_name);
}

function _make_no_student($value) {
    $date = DBDate();

    $stu_schedule_qr = DBGet(DBQuery('SELECT count(ss.college_roll_no) AS TOT FROM students s,course_periods cp,schedule ss ,student_enrollment ssm WHERE ssm.COLLEGE_ROLL_NO=s.COLLEGE_ROLL_NO AND ssm.COLLEGE_ROLL_NO=ss.COLLEGE_ROLL_NO AND ssm.COLLEGE_ID=' . UserCollege() . ' AND ssm.SYEAR=' . UserSyear() . ' AND ssm.SYEAR=cp.SYEAR AND ssm.SYEAR=ss.SYEAR AND (ss.END_DATE>=\'' . $date . '\' OR ss.END_DATE IS NULL) AND cp.COURSE_PERIOD_ID=\'' . $value . '\' AND cp.COURSE_ID=ss.COURSE_ID AND cp.COURSE_PERIOD_ID=ss.COURSE_PERIOD_ID AND (\'' . $date . '\'<=ssm.END_DATE OR ssm.END_DATE IS NULL) AND (\'' . $date . '\'<=ss.END_DATE OR ss.END_DATE IS NULL)'));
    return $stu_schedule_qr[1]['TOT'];
}

function _makeSection($value) {
    if ($value != '') {
        $section = DBGet(DBQuery('SELECT * FROM college_gradelevel_sections WHERE ID=' . $value));
        $section = $section[1]['NAME'];
    } else
        $section = '';
    return $section;
}

?>