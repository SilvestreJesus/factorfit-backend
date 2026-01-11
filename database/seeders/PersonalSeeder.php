

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PersonalSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('personal')->insert([
            [
                'clave_personal' => 'PERS001',
                'nombre_completo' => 'Luis Hernández',
                'puesto' => 'Entrenador',
                'descripcion' => 'Entrenador certificado con 5 años de experiencia',
                'sede' => 'Emiliano',
                'ruta_imagen' => null,
                'rol' => 'personal',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'clave_personal' => 'PERS002',
                'nombre_completo' => 'Ana Torres',
                'puesto' => 'Recepcionista',
                'descripcion' => 'Atención al cliente y soporte',
                'sede' => 'Norte',
                'ruta_imagen' => null,
                'rol' => 'personal',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
