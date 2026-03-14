# Mauricio Ojeda - Portfolio

A minimalist and elegant portfolio website built with Astro, showcasing my projects and skills as a full-stack developer.

## 🚀 Features

- **Minimalist Design**: Clean, elegant interface with focus on content
- **Fully Responsive**: Works perfectly on all devices
- **Dark Mode Support**: Automatic dark mode based on system preferences
- **Fast Performance**: Built with Astro for optimal loading speeds
- **SEO Optimized**: Proper meta tags and semantic HTML
- **GitHub Pages Ready**: Configured for easy deployment

## 🛠️ Tech Stack

- **Astro** - Static Site Generator
- **TypeScript** - Type safety
- **CSS** - Custom styling with CSS variables
- **Google Fonts** - Inter font family

## 📁 Project Structure

```
portfolio/
├── public/              # Static assets
├── src/
│   ├── components/      # Reusable components
│   │   ├── Header.astro
│   │   ├── Footer.astro
│   │   └── ProjectCard.astro
│   ├── layouts/         # Page layouts
│   │   └── Layout.astro
│   └── pages/           # Site pages
│       ├── index.astro
│       ├── projects.astro
│       ├── about.astro
│       ├── contact.astro
│       └── projects/
│           └── electivoia.astro
└── astro.config.mjs     # Astro configuration
```

## 🚀 Getting Started

### Prerequisites

- Node.js 18+ installed
- npm or yarn package manager

### Installation

1. Clone the repository:
```bash
git clone https://github.com/maur-ojeda/portfolio.git
cd portfolio
```

2. Install dependencies:
```bash
npm install
```

3. Start the development server:
```bash
npm run dev
```

4. Open your browser and visit `http://localhost:4321`

## 📝 Available Scripts

- `npm run dev` - Start development server
- `npm run build` - Build for production
- `npm run preview` - Preview production build locally

## 🌐 Deployment to GitHub Pages

### Option 1: Manual Deployment

1. Build the project:
```bash
npm run build
```

2. The output will be in the `dist/` folder

3. Deploy the `dist/` folder to GitHub Pages

### Option 2: Automated Deployment with GitHub Actions

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to GitHub Pages

on:
  push:
    branches: [ main ]
  workflow_dispatch:

permissions:
  contents: read
  pages: write
  id-token: write

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4
      
      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: 20
      
      - name: Install dependencies
        run: npm ci
      
      - name: Build
        run: npm run build
      
      - name: Upload artifact
        uses: actions/upload-pages-artifact@v3
        with:
          path: ./dist

  deploy:
    environment:
      name: github-pages
      url: ${{ steps.deployment.outputs.page_url }}
    runs-on: ubuntu-latest
    needs: build
    steps:
      - name: Deploy to GitHub Pages
        id: deployment
        uses: actions/deploy-pages@v4
```

Then:
1. Push to GitHub
2. Go to repository Settings → Pages
3. Set Source to "GitHub Actions"
4. Your site will be live at `https://maur-ojeda.github.io/portfolio`

## 🎨 Customization

### Update Personal Information

1. **Header Component** (`src/components/Header.astro`):
   - Update logo initials

2. **Footer Component** (`src/components/Footer.astro`):
   - Update social links
   - Update email address

3. **Home Page** (`src/pages/index.astro`):
   - Update name and description
   - Update skills and technologies

4. **About Page** (`src/pages/about.astro`):
   - Update personal information
   - Update experience and education

5. **Contact Page** (`src/pages/contact.astro`):
   - Update contact methods
   - Update form action URL (use Formspree or similar)

### Add New Projects

1. Create a new file in `src/pages/projects/your-project.astro`
2. Add the project to the projects array in `src/pages/projects.astro`
3. Add the project to featured projects in `src/pages/index.astro` (optional)

### Customize Colors

Edit CSS variables in `src/layouts/Layout.astro`:

```css
:root {
  --color-accent: #2563eb;  /* Primary color */
  --color-accent-hover: #1d4ed8;  /* Hover state */
  /* ... other variables */
}
```

## 📄 License

This project is open source and available under the MIT License.

## 🤝 Contact

Mauricio Ojeda
- GitHub: [@maur-ojeda](https://github.com/maur-ojeda)
- Email: mauricio@example.com

---

Built with ❤️ using [Astro](https://astro.build)
