<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InsertBillingCountriesData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('billing_countries')->insert($this->getCountries());
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('billing_countries')->delete();
    }

    public function getCountries()
    {
        return [
        [
            "id" => 1,
            "name" => "Afghanistan",
            "code" => "AF ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 2,
            "name" => "American Samoa",
            "code" => "AS ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 3,
            "name" => "Azerbaijan",
            "code" => "AZ ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 4,
            "name" => "Bangladesh",
            "code" => "BD ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 5,
            "name" => "Barbados",
            "code" => "BB ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 6,
            "name" => "Belarus",
            "code" => "BY ",
            "currency" => "RUB",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 7,
            "name" => "Bhutan",
            "code" => "BT ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 8,
            "name" => "Cayman Islands",
            "code" => "KY ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 9,
            "name" => "China",
            "code" => "CN ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 10,
            "name" => "Cyprus",
            "code" => "CY ",
            "currency" => "EUR",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 11,
            "name" => "Czech Republic",
            "code" => "CZ ",
            "currency" => "EUR",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 12,
            "name" => "Ecuador",
            "code" => "EC ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 13,
            "name" => "Greece",
            "code" => "GR ",
            "currency" => "EUR",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 14,
            "name" => "Guam",
            "code" => "GU ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 15,
            "name" => "Guatemala",
            "code" => "GT ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 16,
            "name" => "Iceland",
            "code" => "IS ",
            "currency" => "EUR",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 17,
            "name" => "Indonesia",
            "code" => "ID ",
            "currency" => "INR",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 18,
            "name" => "Israel",
            "code" => "IL ",
            "currency" => "ILS",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 19,
            "name" => "Japan",
            "code" => "JP ",
            "currency" => "JPY",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 20,
            "name" => "Korea, Republic of",
            "code" => "KR ",
            "currency" => "KRW",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 21,
            "name" => "Maldives",
            "code" => "MV ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 22,
            "name" => "Mali",
            "code" => "ML ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 23,
            "name" => "Mauritania",
            "code" => "MR ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 24,
            "name" => "Mexico",
            "code" => "MX ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 25,
            "name" => "Oman",
            "code" => "OM ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 26,
            "name" => "Pakistan",
            "code" => "PK ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 27,
            "name" => "Palau",
            "code" => "PW ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 28,
            "name" => "Qatar",
            "code" => "QA ",
            "currency" => "EUR",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 29,
            "name" => "Russian Federation",
            "code" => "RU ",
            "currency" => "RUB",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 30,
            "name" => "Saudi Arabia",
            "code" => "SA ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 31,
            "name" => "Singapore",
            "code" => "SG ",
            "currency" => "SGD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 32,
            "name" => "Somalia",
            "code" => "SO ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 33,
            "name" => "South Africa",
            "code" => "ZA ",
            "currency" => "ZAR",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 34,
            "name" => "Sudan",
            "code" => "SD ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 35,
            "name" => "Syrian Arab Republic",
            "code" => "SY ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 36,
            "name" => "Taiwan",
            "code" => "TW ",
            "currency" => "TWD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 37,
            "name" => "Tuvalu",
            "code" => "TV ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 38,
            "name" => "Ukraine",
            "code" => "UA ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 39,
            "name" => "Uzbekistan",
            "code" => "UZ ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 40,
            "name" => "Yemen",
            "code" => "YE ",
            "currency" => "USD",
            "merchant" => "unicrypt",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 41,
            "name" => "Åland Islands",
            "code" => "AX ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 42,
            "name" => "Albania",
            "code" => "AL ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 43,
            "name" => "Algeria",
            "code" => "DZ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 44,
            "name" => "Andorra",
            "code" => "AD ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 45,
            "name" => "Angola",
            "code" => "AO ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 46,
            "name" => "Anguilla",
            "code" => "AI ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 47,
            "name" => "Antarctica",
            "code" => "AQ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 48,
            "name" => "Antigua and\/or Barbuda",
            "code" => "AG ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 49,
            "name" => "Argentina",
            "code" => "AR ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 50,
            "name" => "Armenia",
            "code" => "AM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 51,
            "name" => "Aruba",
            "code" => "AW ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 52,
            "name" => "Australia",
            "code" => "AU ",
            "currency" => "AUD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 53,
            "name" => "Austria",
            "code" => "AT ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 54,
            "name" => "Bahamas",
            "code" => "BS ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 55,
            "name" => "Bahrain",
            "code" => "BH ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 56,
            "name" => "Belgium",
            "code" => "BE ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 57,
            "name" => "Belize",
            "code" => "BZ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 58,
            "name" => "Benin",
            "code" => "BJ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 59,
            "name" => "Bermuda",
            "code" => "BM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 60,
            "name" => "Bolivia",
            "code" => "BO ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 61,
            "name" => "Bosnia and Herzegovina",
            "code" => "BA ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 62,
            "name" => "Botswana",
            "code" => "BW ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 63,
            "name" => "Bouvet Island",
            "code" => "BV ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 64,
            "name" => "Brazil",
            "code" => "BR ",
            "currency" => "BRL",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 65,
            "name" => "British lndian Ocean Territory",
            "code" => "IO ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 66,
            "name" => "Brunei Darussalam",
            "code" => "BN ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 67,
            "name" => "Bulgaria",
            "code" => "BG ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 68,
            "name" => "Burkina Faso",
            "code" => "BF ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 69,
            "name" => "Burundi",
            "code" => "BI ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 70,
            "name" => "Cabo Verde",
            "code" => "CPV",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 71,
            "name" => "Cambodia",
            "code" => "KH ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 72,
            "name" => "Cameroon",
            "code" => "CM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 73,
            "name" => "Canada",
            "code" => "CA ",
            "currency" => "CAD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 74,
            "name" => "Cape Verde",
            "code" => "CV ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 75,
            "name" => "Central African Republic",
            "code" => "CF ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 76,
            "name" => "Chad",
            "code" => "TD ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 77,
            "name" => "Chile",
            "code" => "CL ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 78,
            "name" => "Christmas Island",
            "code" => "CX ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 79,
            "name" => "Cocos (Keeling) Islands",
            "code" => "CC ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 80,
            "name" => "Colombia",
            "code" => "CO ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 81,
            "name" => "Comoros",
            "code" => "KM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 82,
            "name" => "Congo",
            "code" => "CG ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 83,
            "name" => "Congo, the Democratic Republic of the",
            "code" => "CD ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 84,
            "name" => "Cook Islands",
            "code" => "CK ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 85,
            "name" => "Costa Rica",
            "code" => "CR ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 86,
            "name" => "Croatia (Hrvatska)",
            "code" => "HR ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 87,
            "name" => "CuraÃ§ao",
            "code" => "CW ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 88,
            "name" => "Denmark",
            "code" => "DK ",
            "currency" => "DKK",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 89,
            "name" => "Djibouti",
            "code" => "DJ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 90,
            "name" => "Dominica",
            "code" => "DM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 91,
            "name" => "Dominican Republic",
            "code" => "DO ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 92,
            "name" => "East Timor",
            "code" => "TP ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 93,
            "name" => "Egypt",
            "code" => "EG ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 94,
            "name" => "El Salvador",
            "code" => "SV ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 95,
            "name" => "Equatorial Guinea",
            "code" => "GQ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 96,
            "name" => "Eritrea",
            "code" => "ER ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 97,
            "name" => "Estonia",
            "code" => "EE ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 98,
            "name" => "Eswatini",
            "code" => "SWZ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 99,
            "name" => "Ethiopia",
            "code" => "ET ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 100,
            "name" => "Falkland Islands (Malvinas)",
            "code" => "FK ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 101,
            "name" => "Faroe Islands",
            "code" => "FO ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 102,
            "name" => "Fiji",
            "code" => "FJ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 103,
            "name" => "Finland",
            "code" => "FI ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 104,
            "name" => "France",
            "code" => "FR ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 105,
            "name" => "France, Metropolitan",
            "code" => "FX ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 106,
            "name" => "French Guiana",
            "code" => "GF ",
            "currency" => "EIR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 107,
            "name" => "French Polynesia",
            "code" => "PF ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 108,
            "name" => "French Southern Territories",
            "code" => "TF ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 109,
            "name" => "Gabon",
            "code" => "GA ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 110,
            "name" => "Gambia",
            "code" => "GM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 241,
            "name" => "Zaire",
            "code" => "ZR ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 111,
            "name" => "Georgia",
            "code" => "GE ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 112,
            "name" => "Germany",
            "code" => "DE ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 113,
            "name" => "Ghana",
            "code" => "GH ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 114,
            "name" => "Gibraltar",
            "code" => "GI ",
            "currency" => "GBP",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 115,
            "name" => "Greenland",
            "code" => "GL ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 116,
            "name" => "Grenada",
            "code" => "GD ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 117,
            "name" => "Guadeloupe",
            "code" => "GP ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 118,
            "name" => "Guinea",
            "code" => "GN ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 119,
            "name" => "Guinea-Bissau",
            "code" => "GW ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 120,
            "name" => "Guyana",
            "code" => "GY ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 121,
            "name" => "Haiti",
            "code" => "HT ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 122,
            "name" => "Heard and Mc Donald Islands",
            "code" => "HM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 123,
            "name" => "Honduras",
            "code" => "HN ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 124,
            "name" => "Hong Kong",
            "code" => "HK ",
            "currency" => "HKD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 125,
            "name" => "Hungary",
            "code" => "HU ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 126,
            "name" => "Ireland",
            "code" => "IE ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 127,
            "name" => "Italy",
            "code" => "IT ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 128,
            "name" => "Ivory Coast",
            "code" => "CI ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 129,
            "name" => "Jamaica",
            "code" => "JM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 130,
            "name" => "Jordan",
            "code" => "JO ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 131,
            "name" => "Kazakhstan",
            "code" => "KZ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 132,
            "name" => "Kenya",
            "code" => "KE ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 133,
            "name" => "Kiribati",
            "code" => "KI ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 134,
            "name" => "Kosovo",
            "code" => "XK ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 135,
            "name" => "Kuwait",
            "code" => "KW ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 136,
            "name" => "Kyrgyzstan",
            "code" => "KG ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 137,
            "name" => "Lao People's Democratic Republic",
            "code" => "LA ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 138,
            "name" => "Latvia",
            "code" => "LV ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 139,
            "name" => "Lebanon",
            "code" => "LB ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 140,
            "name" => "Lesotho",
            "code" => "LS ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 141,
            "name" => "Liberia",
            "code" => "LR ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 142,
            "name" => "Libyan Arab Jamahiriya",
            "code" => "LY ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 143,
            "name" => "Liechtenstein",
            "code" => "LI ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 144,
            "name" => "Lithuania",
            "code" => "LT ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 145,
            "name" => "Luxembourg",
            "code" => "LU ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 146,
            "name" => "Macau",
            "code" => "MO ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 147,
            "name" => "Macedonia",
            "code" => "MK ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 148,
            "name" => "Madagascar",
            "code" => "MG ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 149,
            "name" => "Malawi",
            "code" => "MW ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 150,
            "name" => "Malaysia",
            "code" => "MY ",
            "currency" => "MYR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 151,
            "name" => "Malta",
            "code" => "MT ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 152,
            "name" => "Marshall Islands",
            "code" => "MH ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 153,
            "name" => "Martinique",
            "code" => "MQ ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 154,
            "name" => "Mauritius",
            "code" => "MU ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 155,
            "name" => "Mayotte",
            "code" => "YT ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 156,
            "name" => "Micronesia, Federated States of",
            "code" => "FM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 157,
            "name" => "Moldova, Republic of",
            "code" => "MD ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 158,
            "name" => "Monaco",
            "code" => "MC ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 159,
            "name" => "Mongolia",
            "code" => "MN ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 160,
            "name" => "Montenegro",
            "code" => "ME ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 161,
            "name" => "Montserrat",
            "code" => "MS ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 162,
            "name" => "Morocco",
            "code" => "MA ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 163,
            "name" => "Mozambique",
            "code" => "MZ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 164,
            "name" => "Myanmar",
            "code" => "MM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 165,
            "name" => "Namibia",
            "code" => "NA ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 166,
            "name" => "Nauru",
            "code" => "NR ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 167,
            "name" => "Nepal",
            "code" => "NP ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 168,
            "name" => "Netherlands",
            "code" => "NL ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 169,
            "name" => "Netherlands Antilles",
            "code" => "AN ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 170,
            "name" => "New Caledonia",
            "code" => "NC ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 171,
            "name" => "New Zealand",
            "code" => "NZ ",
            "currency" => "NZD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 172,
            "name" => "Nicaragua",
            "code" => "NI ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 173,
            "name" => "Niger",
            "code" => "NE ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 174,
            "name" => "Nigeria",
            "code" => "NG ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 175,
            "name" => "Niue",
            "code" => "NU ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 176,
            "name" => "Norfork Island",
            "code" => "NF ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 177,
            "name" => "Northern Mariana Islands",
            "code" => "MP ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 178,
            "name" => "Norway",
            "code" => "NO ",
            "currency" => "NOK",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 179,
            "name" => "Panama",
            "code" => "PA ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 180,
            "name" => "Papua New Guinea",
            "code" => "PG ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 181,
            "name" => "Paraguay",
            "code" => "PY ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 182,
            "name" => "Peru",
            "code" => "PE ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 183,
            "name" => "Philippines",
            "code" => "PH ",
            "currency" => "PHP",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 184,
            "name" => "Pitcairn",
            "code" => "PN ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 185,
            "name" => "Poland",
            "code" => "PL ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 186,
            "name" => "Portugal",
            "code" => "PT ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 187,
            "name" => "Puerto Rico",
            "code" => "PR ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 188,
            "name" => "Reunion",
            "code" => "RE ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 189,
            "name" => "Romania",
            "code" => "RO ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 190,
            "name" => "Rwanda",
            "code" => "RW ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 191,
            "name" => "Saint Kitts and Nevis",
            "code" => "KN ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 192,
            "name" => "Saint Lucia",
            "code" => "LC ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 193,
            "name" => "Saint Vincent and the Grenadines",
            "code" => "VC ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 194,
            "name" => "Samoa",
            "code" => "WS ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 195,
            "name" => "San Marino",
            "code" => "SM ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 196,
            "name" => "Sao Tome and Principe",
            "code" => "ST ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 197,
            "name" => "Senegal",
            "code" => "SN ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 198,
            "name" => "Serbia",
            "code" => "RS ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 199,
            "name" => "Seychelles",
            "code" => "SC ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 200,
            "name" => "Sierra Leone",
            "code" => "SL ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 201,
            "name" => "Sint Maarten (Dutch part)",
            "code" => "SX ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 202,
            "name" => "Slovakia",
            "code" => "SK ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 203,
            "name" => "Slovenia",
            "code" => "SI ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 204,
            "name" => "Solomon Islands",
            "code" => "SB ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 205,
            "name" => "South Georgia South Sandwich Islands",
            "code" => "GS ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 206,
            "name" => "Spain",
            "code" => "ES ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 207,
            "name" => "Sri Lanka",
            "code" => "LK ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 208,
            "name" => "St. Helena",
            "code" => "SH ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 209,
            "name" => "St. Pierre and Miquelon",
            "code" => "PM ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 210,
            "name" => "Suriname",
            "code" => "SR ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 211,
            "name" => "Svalbarn and Jan Mayen Islands",
            "code" => "SJ ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 212,
            "name" => "Swaziland",
            "code" => "SZ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 213,
            "name" => "Sweden",
            "code" => "SE ",
            "currency" => "SEK",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 214,
            "name" => "Switzerland",
            "code" => "CH ",
            "currency" => "CHF",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 215,
            "name" => "Tajikistan",
            "code" => "TJ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 216,
            "name" => "Tanzania, United Republic of",
            "code" => "TZ ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 217,
            "name" => "Thailand",
            "code" => "TH ",
            "currency" => "THB",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 218,
            "name" => "Togo",
            "code" => "TG ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 219,
            "name" => "Tokelau",
            "code" => "TK ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 220,
            "name" => "Tonga",
            "code" => "TO ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 221,
            "name" => "Trinidad and Tobago",
            "code" => "TT ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 222,
            "name" => "Tunisia",
            "code" => "TN ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 223,
            "name" => "Turkey",
            "code" => "TR ",
            "currency" => "TRY",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 224,
            "name" => "Turkmenistan",
            "code" => "TM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 225,
            "name" => "Turks and Caicos Islands",
            "code" => "TC ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 226,
            "name" => "Uganda",
            "code" => "UG ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 227,
            "name" => "United Arab Emirates",
            "code" => "AE ",
            "currency" => "AED",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 228,
            "name" => "United Kingdom",
            "code" => "GB ",
            "currency" => "GBP",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 229,
            "name" => "United States",
            "code" => "US ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 230,
            "name" => "United States minor outlying islands",
            "code" => "UM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 231,
            "name" => "Uruguay",
            "code" => "UY ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 232,
            "name" => "Vanuatu",
            "code" => "VU ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 233,
            "name" => "Vatican City State",
            "code" => "VA ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 234,
            "name" => "Venezuela",
            "code" => "VE ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 235,
            "name" => "Vietnam",
            "code" => "VN ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 236,
            "name" => "Virgin Islands (British)",
            "code" => "VG ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 237,
            "name" => "Virgin Islands (U.S.)",
            "code" => "VI ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 238,
            "name" => "Wallis and Futuna Islands",
            "code" => "WF ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 239,
            "name" => "Western Sahara",
            "code" => "EH ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 240,
            "name" => "Yugoslavia",
            "code" => "YU ",
            "currency" => "EUR",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 242,
            "name" => "Zambia",
            "code" => "ZM ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ],
        [
            "id" => 243,
            "name" => "Zimbabwe",
            "code" => "ZW ",
            "currency" => "USD",
            "merchant" => "ipaytotal",
            "created_at" => null,
            "updated_at" => null
        ]
    ];

    }

}
