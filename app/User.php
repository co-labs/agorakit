<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Watson\Validating\ValidatingTrait;

class User extends Authenticatable
{

  use ValidatingTrait;

  /**
  * The attributes that are mass assignable.
  *
  * @var array
  */
  protected $fillable = [
    'name', 'email', 'password',
  ];

  /**
  * The attributes excluded from the model's JSON form.
  *
  * @var array
  */
  protected $hidden = [
    'password', 'remember_token',
  ];


  protected $rules = [
    'name' => 'required|unique:users',
    'email' => 'required|email|unique:users',
    'password' => 'required',
  ];

  /**
  * The database table used by the model.
  *
  * @var string
  */
  protected $table = 'users';




  /**
  * Boot the model.
  *
  * @return void
  */
  public static function boot()
  {
    parent::boot();
    static::creating(function ($user) {
      $user->token = str_random(30);
    });
  }


  /**
  * Confirm the user.
  *
  * @return void
  */
  public function confirmEmail()
  {
    $this->verified = true;
    $this->token = null;
    $this->save();
  }

  /**
  * Returns true if the user is member of $group
  */
  public function isMemberOf(Group $group)
  {
    $membership = \App\Membership::where('user_id', '=', $this->id)->where('group_id', '=', $group->id)->first();

    if ($membership && $membership->membership > 10)
    {
      return true;
    }

    return false;

  }


  /**
  * The groups this user is part of.
  */
  public function groups()
  {
    return $this->belongsToMany('App\Group', 'membership')->where('membership.membership', '>', '10')->withTimestamps();
  }

  public function memberships()
  {
    return $this->hasMany('App\Membership');
  }


  public function discussionsSubscribed()
  {
    return $this->hasManyThrough('App\Discussion', 'App\Group');
  }

  /**
  * Discussions by this user.
  */
  public function discussions()
  {
    return $this->hasMany('App\Discussion');
  }

  public function avatar()
  {
    return url('/users/' . $this->id . '/avatar');
  }


  public function cover()
  {
    return url('/users/' . $this->id . '/cover');
  }


}
