<style type="text/css">

    .axilent-table {
        width: 80%;
        border: 1px solid #ccc;
    }
    
    .axilent-table thead {
        background-color: #eee;
    }
    
    .axilent-table thead tr th {
        padding: 5px;
    }
    
    .axilent-table tbody tr td {
        padding: 3px;
    }
    
    .axilent-table input {
        width: 100%;
    }
    
    input[type="submit"] {
        margin-top: 10px;
    }
    
    #logo {
        margin-top: 20px;
    }
    
    #widget-console {
        background-color: white;
        font-family:monospace;
        border: 2px dotted #cd5a5a;
        padding: 7px;
        white-space: pre-wrap;
        width: 80%;
    }
</style>

<img id="logo" src="<?php echo get_bloginfo('url') . '/wp-content/plugins/axilent/static/axilent.png' ?>" />

<h2>Axilent Settings</h2>

<?php if($updated): ?>
    <p class="message">
        Your settings have been updated.
    </p>
<?php endif; ?>

<p>
    This is the Axilent settings page. You must have an Axilent account
    in order to use this plugin.
</p>

<form method="post">
<h3>General Axilent Configuration</h3>

<table class="axilent-table">
    <thead>
        <tr>
            <th>Settings</th>
            <th>Values</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Axilent Project Name</td>
            <td><input type="text" name="axilent_project_name" value="<?php echo $axilent_project_name; ?>" /></td>
        </tr>
        <tr>
            <td>Library Key</td>
            <td><input type="text" name="axilent_library_key" value="<?php echo $axilent_library_key ?>" /></td>
        </tr>
        <tr>
            <td>Deployment Key</td>
            <td><input type="text" name="axilent_deployment_key" value="<?php echo $axilent_deployment_key ?>" /></td>
        </tr>
        <tr>
            <td>Sync posts to Axilent</td>
            <td><input type="checkbox" name="axilent_sync" value="true" <?php if($axilent_sync == "yes" || !$axilent_sync) echo "checked" ?> /></td>
        </tr>
        <tr>
            <td>Sync Title Field</td>
            <td><input type="text" name="axilent_title_field" value="<?php echo $axilent_title_field ? $axilent_title_field : 'title' ?>" /></td>
        </tr>
        <tr>
            <td>Sync Content Field</td>
            <td><input type="text" name="axilent_content_field" value="<?php echo $axilent_content_field ? $axilent_content_field : 'content' ?>" /></td>
        </tr>
        <tr>
            <td>Sync Description Field</td>
            <td><input type="text" name="axilent_description_field" value="<?php echo $axilent_description_field ? $axilent_description_field : 'description' ?>" /></td>
        </tr>
        <tr>
            <td>Sync Link Field</td>
            <td><input type="text" name="axilent_link_field" value="<?php echo $axilent_link_field ? $axilent_link_field : 'link' ?>" /></td>
        </tr>
    </tbody>
</table>
<input type="submit" name="axilent_submit" value="Save All Settings" />
    
<h3>Axilent Portlet Keys (per user)</h3>
<table class="axilent-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Portlet Key</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($axilent_users as $user): ?>
        <tr>
            <td><?php echo $user->display_name ?></td>
            <td><?php echo $user->user_login ?></td>
            <td><input type="text" name="users[<?php echo $user->ID ?>]" value="<?php echo $user->portlet_key ?>" /></td>
        </tr>
        <?php endforeach;?>
    </tbody>
</table>
<input type="submit" name="axilent_submit" value="Save All Settings" />

<h3>Debug: Test All Widgets and Content Posting</h3>
<p>Note: This will attempt to make a test post on your Axilent account which should be deleted.</p>

<?php if($widget_errors): ?>
<pre id="widget-console">
<?php foreach($widget_errors as $error): ?>
<?php echo $error.PHP_EOL; ?>
<?php endforeach; ?>
</pre>
<?php endif; ?>
<?php if(!$widget_errors && isset($_POST['axilent_widget_test'])): ?>
<pre id="widget-console">No errors found: <?php echo Axilent_Net::$lastStatus ?></pre>
<?php endif; ?>

<form method="post">
    <input type="submit" name="axilent_widget_test" value="Test Settings" />
</form>

</form>
<?php
