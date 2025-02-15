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
function SubmitButton($value='Submit',$name='',$options='')
{
	if(AllowEdit() || $_SESSION['take_mssn_attn'])
		return "<INPUT type=submit value='$value'".($name?" name='$name'":'').($options?' '.$options:'').">";
	else
		return '';
}
function SubmitButtonModal($value='Submit',$name='',$options='')
{
	
		return "<INPUT type=submit value='$value'".($name?" name='$name'":'').($options?' '.$options:'').">";
	
}

function ResetButton($value='Reset',$options='')
{
	if(AllowEdit())
		return "<INPUT type=reset value='$value'".($options?' '.$options:'').'>';
	else
		return '';
}
?>