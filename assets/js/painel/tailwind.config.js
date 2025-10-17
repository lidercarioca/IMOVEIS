/**
 * Configuração do Tailwind CSS para o sistema
 * Define os arquivos monitorados, cores personalizadas e fontes do projeto
 * 
 * @type {import('tailwindcss').Config}
 */
module.exports = {
  // Arquivos monitorados para compilação do CSS
  content: [
    "./painel.php",         // Página principal
    "./*.php",              // Outras páginas PHP no diretório raiz
    "./components/**/*.php" // Componentes PHP organizados em pastas
  ],
  theme: {
    extend: {
      // Paleta de cores personalizada
      colors: {
        primary: "#1E40AF",     // Azul principal da marca
        secondary: "#F59E0B",   // Amarelo para destaques
        neutral: "#F3F4F6"      // Cinza claro para fundos
      },
      // Configuração de fontes
      fontFamily: {
        sans: ["Poppins", "sans-serif"] // Fonte principal do sistema
      }
    }
  },
  plugins: [] // Plugins adicionais do Tailwind (nenhum no momento)
}
