<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    /**
     * Related with child
     */
    public function children() {
        return $this->hasMany($this,'parent_id');
    }
}
