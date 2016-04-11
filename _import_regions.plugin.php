<?php

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


class import_regions_plugin extends Plugin
{
    /**
     * Variables below MUST be overriden by plugin implementations,
     * either in the subclass declaration or in the subclass constructor.
     */
    var $name = 'Import Regional Data';
    var $code = 'impreg';
    var $priority = 50;
    var $version = '0.1-dev';
    var $author = '';
    var $help_url = '';
    var $group = '';

    var $number_of_installs = 1;


    /**
     * Init: This gets called after a plugin has been registered/instantiated.
     */
    function PluginInit( & $params )
    {
        $this->short_desc = $this->T_('Import regional data');
        $this->long_desc = $this->T_('Let you import regional data into the b2evolution\'s database.');
    }


    /**
     * Event handler: Called when displaying the block in the "Tools" menu.
     *
     * @see Plugin::AdminToolPayload()
     */
    function AdminToolPayload( $params )
    {
        $action = param_action();

        switch( $action ) {
            default:
                $Form = new Form();

                $Form->begin_form( 'fform' );

                $Form->add_crumb( 'tools' );
                $Form->hidden_ctrl(); // needed to pass the "ctrl=tools" param
                $Form->hiddens_by_key( get_memorized() ); // needed to pass all other memorized params, especially "tab"
                $Form->hidden( 'action', 'import_regional_data' );

                $Form->input_field( array( 'label' => T_('Source file'), 'note' => T_('CSV file to be imported'), 'name' => 'src_file', 'type' => 'file' ) );
                $Form->input_field( array( 'value' => T_('Import regional data'), 'name' => 'submit', 'type' => 'submit' ) );

                $Form->end_form();

                break;
        }
    }


    /**
     * Event handler: Called when handling actions for the "Tools" menu.
     *
     * Use {@link $Messages} to add Messages for the user.
     *
     * @see Plugin::AdminToolAction()
     */
    function AdminToolAction()
    {
        $action = param_action();

        if (!empty($action)) {    // If form is submitted
            global $DB;

            switch ($action) {
                case 'import_regional_data':
                    // Do the import
                    break;
            }
        }
    }
}

?>