<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends BaseModel
{

    use SoftDeletes;

    public function answers()
    {
    	return $this->hasMany('App\Models\Answer');
    }

    public function classes()
    {
        return $this->belongsTo('App\Classes', 'class_id');
    }

    public function question_type()
    {
        return $this->belongsTo('App\QuestionType', 'question_type_id');
    }


    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id');
    }


    public function lesson()
    {
    	return $this->belongsTo('App\Lesson');
    }

    public function pretest_course()
    {
    	return $this->belongsTo('App\Course');
    }
}
