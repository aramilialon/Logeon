<?php

namespace App\Models;

use Core\Models;

class GuildRequirement extends Models
{
    protected $table = 'guild_requirements';
    protected $primary_key = 'id';
    protected $joins = [
        ' LEFT JOIN guilds ON guild_requirements.guild_id = guilds.id ',
        " LEFT JOIN jobs ON guild_requirements.type = 'job_id' AND jobs.id = guild_requirements.value ",
    ];
    protected $fillable = [
        'guild_requirements.*',
        'guilds.name AS guild_name',
        'jobs.name AS job_name',
    ];
}
