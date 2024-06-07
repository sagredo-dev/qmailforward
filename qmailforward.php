<?php
/**
 * qmailforward
 *
 * Plugin to build the forward and copy for qmail
 * vpopmail must be patched accordingly and configured to store the
 * aliases in the database (--enable-valias). Adjustments to
 * qmailadmin are needed as well.
 *
 * @version 1.0.2
 * @author Roberto Puzzanghera
 * @url https://notes.sagredo.eu/en/qmail-notes-185/roundcube-plugins-35.html#qmailforward
 *      https://notes.sagredo.eu/en/qmail-notes-185/sql-valias-with-sieve-solution-for-qmail-new-patches-and-roundcube-plugin-301.html
 *
 * Credits for the scheme of several functions and classes go to the
 * sauserprefs and managesieve authors. They are specified before the
 * relevant code.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Roundcube. If not, see https://www.gnu.org/licenses/.
 */

class qmailforward extends rcube_plugin
{
    public  $task = 'settings';
    private $storage;
    private $user;
    private $domain;
    private $error;

    public function init()
    {
        $this->rc = rcmail::get_instance();
        $this->load_config('config.inc.dist.php');
        $this->load_config();

        if ($this->plugin_enabled($this->domain)) {

            if (!$this->get_username()) {
                rcube::write_log('errors', 'qmailforward error: cannot retrieve username/domain');
                return 1;
            }

            $this->add_texts('localization/', true);
            $this->include_script('qmailforward.js');

            // insert the menu button
            $this->add_hook('settings_actions', [$this, 'settings_tab']);

            $this->_init_storage();
            $this->register_action('plugin.qmailforward', [$this, 'init_html']);
            $this->register_action('plugin.qmailforward-save', [$this, 'forward_save']);
        }
    }


    /* allow qmailforward_allowed_hosts only, unless that array is empty */
    private function plugin_enabled($domain) {
        $denied_domains = $this->rc->config->get('qmailforward_allowed_hosts');
        if (in_array($domain, $denied_domains)) return false;
        else return true;
    }


    /* build the html */
    public function init_html()
    {
        // page title
        $this->rc->output->set_pagetitle($this->gettext('page-title'));

        // register content handler
        // 'forwardform' = container's name/id inside template
        $this->rc->output->add_handler('forwardform', array($this, 'forward_form'));

        // send content to template 'form_template'
        $this->rc->output->send('qmailforward.form_template');
    }


    /**********************************************************************************************
     * forward form
     *
     * function derived from the RC managesieve-forward plugin, which already has a nice
     * html form
     *
     * $attrib is an array containing the xml attrib of the rc obj <roundcube:object name="forwardform" id="forwardform" class="propform" />
     * array(3) { ["name"]=> string(11) "forwardform" ["id"]=> string(11) "forwardform" ["class"]=> string(8) "propform" }
     *
     **********************************************************************************************/
    public function forward_form($attrib)
    {
        $stored = array(); // container array for db data
        // load data from the db
        $stored = $this->storage->load($this->user, $this->domain);

        // build FORM tag
        $form_id = $attrib['id']; // = forwardform
        $out     = $this->rc->output->request_form([
                'id'      => $form_id,
                'name'    => $form_id,
                'method'  => 'post',
                'task'    => 'settings',
                'action'  => 'plugin.qmailforward-save', // we have registered an action for that to handle the post
                'noclose' => true
            ] + $attrib
        );

        // form elements
        $status = new html_select(['name' => 'forward_status', 'id' => 'forward_status', 'class' => 'custom-select']);
        $action = new html_select(['name' => 'forward_action', 'id' => 'forward_action', 'class' => 'custom-select']);

        $status->add($this->gettext('on'), 'on');
        $status->add($this->gettext('off'), 'off');

        $action->add($this->gettext('copy'), 'copy');
        $action->add($this->gettext('redirect'), 'redirect');

        // force domain selection in redirect email input
        $domains  = (array) $this->rc->config->get('qmailforward_domains');

        if (!empty($domains)) {
            sort($domains);

            $domain_select = new html_select(['name' => 'action_domain', 'id' => 'action_domain', 'class' => 'custom-select']);
            $domain_select->add(array_combine($domains, $domains));

            if (!empty($stored)) {
                $parts = explode('@', $stored[$this->rc->config->get('qmailforward_sql_valias_field')]);
                if (!empty($parts)) {
                    $the_domain   = in_array($parts[1], $domains) ? $parts[1] : '';
                    $the_username = !empty($the_domain) ? $parts[0] : '';
                }
            }
        }

        // the value is the entire address, unless we are allowing only a set of domains
        $target_value = !empty($domain_select) ? $the_username
                        : $stored[$this->rc->config->get('qmailforward_sql_valias_field')]??'';

        // redirect target
        $domain_selected = !empty($the_domain) ? $the_domain : null;
        $action_target = '<span id="action_target_span" class="input-group">'
            . '<input type="text" name="action_target" id="action_target"'
            . ' value="' .$target_value. '"'
            . (!empty($domain_select) ? ' size="20"' : ' size="35"') . '/>'
            . (!empty($domain_select) ? ' <span class="input-group-prepend input-group-append"><span class="input-group-text">@</span></span> '
                                        . $domain_select->show($domain_selected)
                                      : '')
            . '</span>';

        // Message tab
        $table = new html_table(['cols' => 2]);

        // forward
        $table->add('title', html::label('forward_action', $this->gettext('action')));
        $copy = ($stored[$this->rc->config->get('qmailforward_sql_copy_field')]??null)==1 ? 'copy' : 'redirect';
        $table->add('forward input-group input-group-combo', $action->show($copy).' '.$action_target);

        // status
        $table->add('title', html::label('forward_status', $this->gettext('status')));
        $on_off = ( empty($stored) || (!empty($domains) && $the_domain=='') ) ? 'off' : 'on';
        $table->add(null, $status->show($on_off));

        $out .= $table->show($attrib);
        $out .= '</form>';

        $this->rc->output->add_gui_object('qmailforward_form', $form_id);

        return $out;
    }


    /**********************************************
     * collect the POST data
     *      forward_action => copy/redirect
     *      action_target  => user@domain.tld (or just user if action_domain defined)
     *      action_domain  =  domain.tld
     *      forward_status => on/off
     **********************************************/
    public function forward_save()
    {
        $success = true;
        $error = '';

        // build email
        $email = !is_null($_POST['action_domain']??null) ?
               $_POST['action_target'].'@'.$_POST['action_domain'] :
               $_POST['action_target'];
        // sanity check
        if (!$this->validEmail($email)) {
            $error = $this->gettext('invalid_email').": ".$email.$_SERVER['REQUEST_METHOD'] ;
            $success = false;
        }

        /***************************************
         * save to db
         *
         * result = true on success
         *          false on error
         ***************************************/
        if (!$this->storage->save($this->user, $this->domain)) $success = false;

        // return feedback
        if ($success) {
            $this->rc->output->command('display_message', $this->gettext('success'), 'confirmation');
        }
        else {
            $error = !empty($error) ? ": ".$error : '';
            $this->rc->output->command('display_message', $this->gettext('failed').$error, 'error');
        }
    }


    /* set the button in the settings menu side bar */
    public function settings_tab($p)
    {
        $this->include_stylesheet($this->local_skin_path() . '/style.css');

        // add qmailforward tab
        $p['actions'][] = [
            'action'        => 'plugin.qmailforward', // we defined an handler for this action
            'class'         => 'qmailforward',
            'label'         => 'qmailforward.forward',
            'title'         => 'qmailforward.manage-forward',
            'role'          => 'button',
            'aria-disabled' => 'false',
            'tabindex'      => '0'];

        return $p;
    }


    /* load the classes for db storage */
    private function _init_storage()
    {
        if (!$this->storage) {
            // Add include path for internal classes
            $include_path = $this->home . '/lib' . \PATH_SEPARATOR;
            $include_path .= ini_get('include_path');
            set_include_path($include_path);

            $class = $this->rc->config->get('qmailforward_storage', 'mysql');
            $class = "rcube_qmailforward_storage_" . $class;

            // try to instantiate class
            if (class_exists($class)) {
                $this->storage = new $class($this->rc->config);
            }
            else {
                // no storage found, raise error
                rcube::raise_error(['code' => 604, 'type' => 'qmailforward',
                    'line' => __LINE__, 'file' => __FILE__,
                    'message' => "Failed to find storage driver. Check qmailforward_storage config option",
                ], true, true);
            }
        }
    }


    /* save the username and the domain */
    private function get_username() {
        $username = explode('@', $_SESSION['username']);
        $this->user   = $username[0];
        $this->domain = $username[1];

        if (!is_null($this->user) && !is_null($this->domain)) return true;
        else return false;
    }


    /********************************************************
     *  http://www.linuxjournal.com/article/9585?page=0,3
     *  Validate an email address.
     *  Provide email address (raw input)
     *  Returns true if the email address has the email
     *  address format and the domain exists.
     ********************************************************/
    private function validEmail($email)
    {
        // DNS check disabled by default
        $checkDNS = $this->rc->config->get('qmailforward_dnscheck');

        $isValid = true;
        $atIndex = strrpos($email, "@");
        if (is_bool($atIndex) && !$atIndex)
        {
            $isValid = false;
        }
        else
        {
            $domain = substr($email, $atIndex+1);
            $local = substr($email, 0, $atIndex);
            $localLen = strlen($local);
            $domainLen = strlen($domain);
            if ($localLen < 1 || $localLen > 64)
            {
                // local part length exceeded
                $isValid = false;
            }
            else if ($domainLen < 1 || $domainLen > 255)
            {
                // domain part length exceeded
                $isValid = false;
            }
            else if ($local[0] == '.' || $local[$localLen-1] == '.')
            {
                // local part starts or ends with '.'
                $isValid = false;
            }
            else if (preg_match('/\\.\\./', $local))
            {
                // local part has two consecutive dots
                $isValid = false;
            }
            else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))
            {
                // character not valid in domain part
                $isValid = false;
            }
            else if (preg_match('/\\.\\./', $domain))
            {
                // domain part has two consecutive dots
                $isValid = false;
            }
            else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
                str_replace("\\\\","",$local)))
            {
                // character not valid in local part unless
                // local part is quoted
                if (!preg_match('/^"(\\\\"|[^"])+"$/',
                    str_replace("\\\\","",$local)))
                {
                    $isValid = false;
                }
            }
            if ($checkDNS && $isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A")))
            {
                // domain not found in DNS
                $isValid = false;
            }
       }
       return $isValid;
    }
}
