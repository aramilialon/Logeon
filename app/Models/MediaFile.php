<?php

namespace App\Models;

use Core\Models;

/**
 * @property int|null $id
 * @property string $stored_name
 * @property string $original_name
 * @property string $extension
 * @property string $mime_type
 * @property int $size
 * @property string $path
 * @property int|null $uploaded_by_user_id
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class MediaFile extends Models
{
    /** @var int|null */
    public $id = null;
    /** @var string */
    public $stored_name = '';
    /** @var string */
    public $original_name = '';
    /** @var string */
    public $extension = '';
    /** @var string */
    public $mime_type = '';
    /** @var int */
    public $size = 0;
    /** @var string */
    public $path = '';
    /** @var int|null */
    public $uploaded_by_user_id = null;
    /** @var string|null */
    public $created_at = null;
    /** @var string|null */
    public $updated_at = null;

    protected $table = 'media_files';
    protected $primary_key = 'id';
    protected $fillable = [
        'id',
        'stored_name',
        'original_name',
        'extension',
        'mime_type',
        'size',
        'path',
        'uploaded_by_user_id',
        'created_at',
        'updated_at',
    ];
}
