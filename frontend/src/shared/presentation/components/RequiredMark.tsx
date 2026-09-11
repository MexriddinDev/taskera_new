import React from 'react';

/**
 * Majburiy maydon belgisi.
 *
 * Ilgari yulduzcha tarjima matnining ichiga yozib qo'yilgandi
 * ("Zayavka boradigan guruh *") va shu yo'l bilan majburiy bo'lmagan
 * maydonlarga ham tarqalib ketgandi. Endi u faqat shu komponent orqali
 * qo'yiladi — ya'ni `required` bo'lgan maydonda va bir xil ko'rinishda.
 *
 * `aria-hidden`: ekran o'quvchisi majburiylikni maydonning `required`
 * atributidan biladi, yulduzchani ikki marta o'qishi shart emas.
 */
export const RequiredMark: React.FC = () => (
  <span aria-hidden="true" className="text-rose-500"> *</span>
);
