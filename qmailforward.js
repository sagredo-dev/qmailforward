if (window.rcmail) {
    rcmail.addEventListener('init', function(evt) {

        // register command
        rcmail.register_command('plugin.qmailforward-save', function() { rcmail.forward_save() });

        // enable command
        if (rcmail.env.action.startsWith('plugin.qmailforward')) {
            if (rcmail.gui_objects.qmailforward_form) {
                rcmail.enable_command('plugin.qmailforward-save', true);
            }
        }
    });
}


// Form submission
rcube_webmail.prototype.forward_save = function()
{
    // post
    if (this.env.action == 'plugin.qmailforward') {
        var data = $(this.gui_objects.qmailforward_form).serialize();
        this.http_post('plugin.qmailforward-save', data, this.display_message(this.gettext('saving', 'qmailforward'), 'loading'));
        return;
    }

    // submit
    if (this.gui_objects.qmailforward_form) {
        this.gui_objects.qmailforward_form.submit();
    }
};
