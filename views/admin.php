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
            <td>API Key</td>
            <td><input type="text" name="axilent_api_key" value="<?php echo $axilent_api_key ?>" /></td>
        </tr>
        <tr>
            <td>Sync posts to Axilent</td>
            <td><input type="checkbox" name="axilent_sync" value="true" <?php if($axilent_sync == "yes" || !$axilent_sync) echo "checked" ?> /></td>
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
</form>
<?php
