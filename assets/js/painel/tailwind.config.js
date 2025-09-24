/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./painel.php",         // Página principal
    "./*.php",              // Outras páginas PHP no diretório raiz
    "./components/**/*.php" // Se você usa componentes PHP organizados em pastas
  ],
  theme: {
    extend: {
      colors: {
        primary: "#1E40AF",     // Azul personalizado
        secondary: "#F59E0B",   // Amarelo para destaques
        neutral: "#F3F4F6"      // Cinza claro para fundo
      },
      fontFamily: {
        sans: ["Poppins", "sans-serif"]
      }
    }
  },
  plugins: []
}
