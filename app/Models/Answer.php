<?php

namespace App\Models;


class Answer extends BaseModel
{
    public function Question()
    {
    	return $this->belongsTo('App\Models\Question');
    }

    public function candidates()
    {
    	return $this->belongsToMany('App\Candidate');
    }

}
