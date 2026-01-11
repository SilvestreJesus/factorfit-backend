<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('usuarios')->insert([
            [
                'clave_usuario' => 'CLI001',
                'nombres' => '',
                'apellidos' => '',
                'fecha_nacimiento' => '1992-08-22',
                'telefono' => '',
                'email' => 'emiliano@factorfit.com',
                'password' => Hash::make('emiliano123'),
                'sede' => 'Emiliano',
                'status' => 'activo',
                'ruta_imagen' => null,
                'qr_imagen' => null,
                'rol' => 'admin1',
                'peso_inicial' => '',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'clave_usuario' => 'CLI002',
                'nombres' => '',
                'apellidos' => '',
                'fecha_nacimiento' =>'1992-08-22',
                'telefono' => '',
                'email' => 'obrera@factorfit.com',
                'password' => Hash::make('obrera123'),
                'sede' => 'Obrera',
                'status' => 'activo',
                'ruta_imagen' => null,
                'qr_imagen' => null,
                'rol' => 'admin2',
                'peso_inicial' => '',
                'created_at' => now(),
                'updated_at' => now()
            ],            
            [
                'clave_usuario' => 'CLI003',
                'nombres' => '',
                'apellidos' => '',
                'fecha_nacimiento' => '1992-08-22',
                'telefono' => '',
                'email' => 'factorfit@factorfit.com',
                'password' => Hash::make('factorfit123'),
                'sede' => 'Emiliano,Obrera',
                'status' => 'activo',
                'ruta_imagen' => null,
                'qr_imagen' => null,
                'rol' => 'superadmin',
                'peso_inicial' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
