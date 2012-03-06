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
    
</style>

<h2>Axilent Settings</h2>

<?php if($wpgh_updated): ?>
    <p class="message">
        Your settings have been updated.
    </p>
<?php endif; ?>

<p>
    This is the Axilent settings page. You should probably have an Axilent
    if you are using this plugin.
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
            <td>Axilent Account Name</td>
            <td><input type="text" name="account_name" /></td>
        </tr>
        <tr>
            <td>Default API Key</td>
            <td><input type="text" name="default_api_key" /></td>
        </tr>
    </tbody>
</table>
<input type="submit" name="axilent_submit" value="Save All Settings" />
    
<h3>Axilent API Keys (per user)</h3>
<table class="axilent-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Axilent API Key</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($axilent_users as $user): ?>
        <tr>
            <td><?php echo $user->display_name ?></td>
            <td><?php echo $user->user_login ?></td>
            <td><input type="text" name="users[<?php echo $user->ID ?>]" value="<?php echo $user->axilent_key ?>" /></td>
        </tr>
        <?php endforeach;?>
    </tbody>
</table>
<input type="submit" name="axilent_submit" value="Save All Settings" />
</form>
<?php
