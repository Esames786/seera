<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = [
        'name', 'name_ar', 'logo_path', 'email', 'phone', 'website',
        'cr_number', 'vat_number', 'zatca_registration_number', 'default_vat_rate',
        'invoice_mode', 'certificate_status', 'country', 'city', 'currency',
        'fiscal_year_start', 'fiscal_year_end', 'address', 'status',
        'street', 'building_number', 'additional_number', 'district', 'postal_code', 'country_code', 'seller_id_scheme',
    ];

    /** Seller identification schemes accepted by BR-KSA-08 (one of, in this order of preference). */
    public const SELLER_ID_SCHEMES = ['CRN', 'MOM', 'MLS', '700', 'SAG', 'OTH'];
}
