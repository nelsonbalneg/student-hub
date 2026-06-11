<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SsoCampus extends Model
{
    protected $connection = 'sso_sqlsrv';

    protected $table = 'campuses';

    public $timestamps = false;

    protected $guarded = [];

    public function displayName(): string
    {
        return (string) (
            $this->getAttribute('name')
            ?? $this->getAttribute('campus_name')
            ?? $this->getAttribute('campusName')
            ?? $this->getAttribute('description')
            ?? 'Campus '.$this->getKey()
        );
    }

    public function tenantId(): ?int
    {
        $tenantId = $this->getAttribute('tenant_id')
            ?? $this->getAttribute('tenantId')
            ?? $this->getAttribute('TenantID')
            ?? $this->getAttribute('id');

        return filled($tenantId) ? (int) $tenantId : null;
    }
}
