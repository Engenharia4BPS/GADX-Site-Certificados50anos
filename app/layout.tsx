import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Araucária DX — 50 anos",
  description: "Consulta e emissão do diploma comemorativo de 50 anos da Araucária DX.",
  other: {
    "codex-preview": "development",
  },
  icons: {
    icon: "/favicon.svg",
    shortcut: "/favicon.svg",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="pt-BR">
      <body className="antialiased">{children}</body>
    </html>
  );
}
