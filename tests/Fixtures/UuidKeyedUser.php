<?php

declare(strict_types=1);

namespace Rache\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * A user model keyed by something other than `id`.
 */
class UuidKeyedUser extends AuthUser
{
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['uuid'];
}
