<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * enrol_teacherskey file description here.
 *
 * @package    enrol_teacherskey
 * @copyright  2022 Alex Sidorov <alex.sidorov@ya.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

class enrol_teacherskey_plugin extends enrol_plugin {

    public function enrol_page_hook(stdClass $instance)
    {
        global $CFG, $OUTPUT, $USER;
        require_once($CFG->dirroot."/enrol/teacherskey/locallib.php");
        $enroll_status = $this->can_self_enrol($instance);
        if (true === $enroll_status){

            $form = new teacherskey_from(null, $instance);
            $instanceid = optional_param('instance', 0, PARAM_INT);
                if ($form->is_cancelled()) {
                    redirect($CFG->wwwroot . "/my", get_string('cancelm', 'enrol_teacherskey'));
                } else if ($data = $form->get_data()) {
                    $this->enrol_self($data, $instance);
                }


        }
        ob_start();
        $form->display();
        $output = ob_get_contents();
        ob_get_clean();
        return $OUTPUT->box($output);

    }

    /**
     * Add new instance of enrol plugin.
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        return parent::add_instance($course, $fields);
    }

    public function add_default_instance($course){
        $fields = $this->get_instance_defaults();
        return $this->add_instance($course, $fields);
    }

    public function get_instance_defaults()
    {
        $fields = array();
        $fields['status'] = $this->get_config('status');
        $fields['roleid'] = $this->get_config('roleid');
        return $fields;
    }

    public function can_add_instance($courseid)
    {
        global $CFG, $DB;

        $coursecontext = context_course::instance($courseid);

        if(!has_capability('moodle/course:enrolconfig', $coursecontext) or !has_capability('enrol/teacherskey:config', $coursecontext)){
            return false;
        }

        return true;

    }

    public function can_self_enrol(stdClass $instance, $checkuserenrolment = true)
    {
        global $DB, $USER, $OUTPUT;

        if($checkuserenrolment){

            if(isguestuser()){
                return get_string('noguestaccess', 'teacherskey').$OUTPUT->contine_button(get_login_url());
            }

            if ($DB->record_exists('teacherskey_data', array('userid' => $USER->id, 'courseid' => $instance->courseid))){
                return get_string('cango', 'teacherskey');
            }


        }

        return true;
    }

    public function get_enrol_info(stdClass $instance)
    {
        return true;
    }

    public function use_standard_editing_ui()
    {
        return true;
    }

    private function enrol_self($data = null, stdClass $instance)
    {
        global $CFG, $DB, $USER;


       $timestart = time();
        if ($instance->enrolperiod) {
            $timeend = $timestart + $instance->enrolperiod;
        } else {
            $timeend = 0;
        }


        if(in_array($data->fio, array(null, false, ''))){
            return;
        }


        $this->enrol_user($instance, $USER->id, $instance->roleid, $timestart, $timeend);

        if(!$DB->record_exists('teacherskey_data', array('courseid' => $instance->courseid, 'userid' => $USER->id))) {

            $save_data = new stdClass();
            $save_data->courseid = $data->id;
            $save_data->userid = $USER->id;
            $save_data->fio = $data->fio;
            $DB->insert_record('teacherskey_data', $save_data);

        }

        \core\notification::success(get_string('youenrolledincourse', 'enrol'));

        $groups = $DB->get_records('groups', array('courseid'=>$instance->courseid), 'id', 'id, enrolmentkey');
        var_dump($groups);
        foreach ($groups as $group) {
                // Add user to group.
                require_once($CFG->dirroot.'/group/lib.php');
                groups_add_member($group->id, $USER->id);
                break;

        }


    }

    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/teacherskey:config', $context);
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'));
        return $options;
    }

    /**
     * Gets a list of roles that this user can assign for the course as the default for self-enrolment.
     *
     * @param context $context the context.
     * @param integer $defaultrole the id of the role that is set as the default for self-enrolment
     * @return array index is the role id, value is the role name
     */
    public function extend_assignable_roles($context, $defaultrole) {
        global $DB;

        $roles = get_assignable_roles($context, ROLENAME_BOTH);
        if (!isset($roles[$defaultrole])) {
            if ($role = $DB->get_record('role', array('id' => $defaultrole))) {
                $roles[$defaultrole] = role_get_name($role, $context, ROLENAME_BOTH);
            }
        }
        return $roles;
    }

    /**
     * Return an array of valid options for the newenrols property.
     *
     * @return array
     */
    protected function get_newenrols_options() {
        $options = array(1 => get_string('yes'), 0 => get_string('no'));
        return $options;
    }

    public function edit_instance_form($instance, MoodleQuickForm $mform, $context)
    {
        global $CFG, $DB;

        $nameattribs = array('size' => '20', 'maxlength' => '255');
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'), $nameattribs);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_self'), $options);
        $mform->addHelpButton('status', 'status', 'enrol_self');

        $options = $this->get_newenrols_options();
        $mform->addElement('select', 'customint6', get_string('newenrols', 'enrol_self'), $options);
        $mform->addHelpButton('customint6', 'newenrols', 'enrol_self');
        $mform->disabledIf('customint6', 'status', 'eq', ENROL_INSTANCE_DISABLED);

        $roles = $this->extend_assignable_roles($context, $instance->roleid);

        $mform->addElement('select', 'roleid', get_string('role', 'enrol_self'), $roles);

    }

}

/**
 * Display the list Teachers in the course menu.
 *
 * @param settings_navigation $navigation The settings navigation object
 * @param stdClass $course The course
 * @param context $context Course context
 */
function enrol_teacherskey_extend_navigation_course($navigation, $course, $context) {
    $url = new moodle_url('/enrol/teacherskey/listteachers.php', ['courseid' => $course->id]);
    $certificatenode = $navigation->add(get_string('teacherslist', 'enrol_teacherskey'), $url,  navigation_node::TYPE_CONTAINER, null, 'enrol_teacherskey');
}

