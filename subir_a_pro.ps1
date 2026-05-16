# Script para subir a Producción
Get-ChildItem -Path "C:\Users\adriru\Documents\Importante\DEV\Ionos\www.adriru.es\" -Exclude ".vscode", "accesos.log" | Remove-Item -Recurse -Force
robocopy C:\Users\adriru\Documents\Importante\DEV\Ionos\dev.adriru.es\ C:\Users\adriru\Documents\Importante\DEV\Ionos\www.adriru.es\ /E /XD .vscode
