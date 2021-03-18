<?php

namespace App\Models;

use Carbon\Carbon;

class Authorization
{
    protected $token;

    protected $payload;

    public function __construct($token = null)
    {
        $this->token = $token;
    }

    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    public function getToken()
    {
        if (! $this->token) {
            throw new \Exception('Please set token');
        }

        return $this->token;
    }

    public function getPayload()
    {
        if (! $this->payload) {
            $this->payload = \Auth::setToken($this->getToken())->getPayload();
        }

        return $this->payload;
    }

    public function getExpiredAt()
    {
        return Carbon::createFromTimestamp($this->getPayload()->get('exp'))
            ->toDateTimeString();
    }

    public function getRefreshExpiredAt()
    {
        return Carbon::createFromTimestamp($this->getPayload()->get('iat'))
            ->addMinutes(config('jwt.refresh_ttl'))
            ->toDateTimeString();
    }

    public function user()
    {
        return \Auth::authenticate($this->getToken());
    }

    public function toArray()
    {
        $this_user = $this->user();
        $this_user->userType = (string)$this_user->role_id;
        $this_user->name = $this_user->name;
        $this_user->status = (bool)( $this_user->status == 1 ? true : false);
        $this_user->createdAt =  !is_null($this_user->created_at)  ? $this_user->created_at->toDateTimeString() : "1970-01-01 00:00:00";
        $this_user->updatedAt = !is_null($this_user->updated_at) ? $this_user->updated_at->toDateTimeString() : "1970-01-01 00:00:00";

        return [
            // maybe you need a id when use jsonapi.org format
            //'id' => hash('md5', $this->getToken()),
            'token' => $this->getToken(),
            'message' => "Token Issued.",
            'user' => $this_user,
            'expired_at' => $this->getExpiredAt(),
            'refresh_expired_at' => $this->getRefreshExpiredAt(),
        ];
    }
}