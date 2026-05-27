<?php

namespace App\Models;

use Core\Models;

class Character extends Models
{
    protected $table = 'characters';
    protected $primary_key = 'id';
    protected $joins = [
        ' LEFT JOIN users ON characters.user_id = users.id ',
        ' LEFT JOIN (SELECT character_id, MIN(job_id) AS job_id FROM character_jobs WHERE is_active = 1 GROUP BY character_id) AS _pj ON _pj.character_id = characters.id ',
        ' LEFT JOIN jobs ON jobs.id = _pj.job_id ',
    ];
    protected $fillable = [
        'characters.*',
        'jobs.name AS job_name',
        'jobs.icon AS job_icon',
    ];
}
