import sys
import qrcode
import os

def generar_qr(clave_usuario):
    # Ruta donde se guardará la imagen dentro del proyecto Laravel
    base_path = os.path.abspath(os.path.join(os.getcwd(), ".."))
    qr_folder = os.path.join(base_path, "public", "storage", "qr")

    # Crear carpeta si no existe
    if not os.path.exists(qr_folder):
        os.makedirs(qr_folder)

    # Nombre del archivo
    file_path = os.path.join(qr_folder, f"{clave_usuario}.png")

    # Contenido del QR
    data = f"USUARIO:{clave_usuario}"

    # Generar QR
    qr = qrcode.make(data)
    qr.save(file_path)

    # Regresar la ruta relativa que Laravel puede servir
    print(f"storage/qr/{clave_usuario}.png")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("ERROR: No se proporcionó clave_usuario")
        sys.exit(1)

    clave = sys.argv[1]
    generar_qr(clave)
