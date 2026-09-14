/**
 * `gremlins.js` (2.2.0) uchun tip deklaratsiyasi.
 *
 * Paket faqat UMD bundle bilan keladi (`dist/gremlins.min.js`), ichida `.d.ts`
 * yo'q va DefinitelyTyped'da ham `@types/gremlins.js` mavjud emas. Shu sabab
 * loyihaga kerak bo'lgan qismi shu yerda qo'lda tavsiflangan.
 *
 * Manba: https://github.com/marmelab/gremlins.js
 */
declare module 'gremlins.js' {
  /** Gremlin va mogwai'larga uzatiladigan umumiy kontekst. */
  interface GremlinContext {
    logger?: Logger;
    randomizer?: unknown;
    window?: Window;
  }

  interface Logger {
    log: (...args: unknown[]) => void;
    info: (...args: unknown[]) => void;
    warn: (...args: unknown[]) => void;
    error: (...args: unknown[]) => void;
  }

  /** Bir marta ishga tushiriladigan gremlin (yoki mogwai) yaratuvchi. */
  type SpeciesFactory = (context: GremlinContext) => unknown;
  type MogwaiFactory = (context: GremlinContext & { stop: () => void }) => unknown;
  type StrategyFactory = (randomizer: unknown) => unknown;

  interface ClickerOptions {
    /** Qaysi hodisalar yuborilsin: 'click', 'dblclick', 'mousedown', ... */
    clickTypes?: string[];
    /** `false` qaytarsa — bu elementga bosilmaydi. */
    canClick?: (element: Element) => boolean;
    positionSelector?: () => [number, number];
    showAction?: (x: number, y: number, type: string) => void;
    logger?: Logger;
    randomizer?: unknown;
  }

  interface FormFillerOptions {
    elementMapTypes?: Record<string, (element: Element) => void>;
    canFillElement?: (element: Element) => boolean;
    maxNbTries?: number;
    showAction?: (element: Element) => void;
    logger?: Logger;
    randomizer?: unknown;
  }

  interface TyperOptions {
    eventTypes?: string[];
    keyGenerator?: () => number;
    targetElement?: () => Element;
    showAction?: (element: Element, x: number, y: number, key: number) => void;
    logger?: Logger;
    randomizer?: unknown;
  }

  interface ScrollerOptions {
    positionSelector?: () => [number, number];
    showAction?: (x: number, y: number) => void;
    logger?: Logger;
    randomizer?: unknown;
  }

  interface ToucherOptions {
    touchTypes?: string[];
    canTouch?: (element: Element) => boolean;
    positionSelector?: () => [number, number];
    showAction?: (touches: unknown) => void;
    logger?: Logger;
    randomizer?: unknown;
  }

  interface GizmoOptions {
    /** Shuncha xatodan keyin hujum to'xtatiladi. */
    maxErrors?: number;
    logger?: Logger;
  }

  interface FpsOptions {
    delay?: number;
    /** FPS shu qiymatdan pastga tushsa — hujum to'xtaydi. */
    levelSelector?: (fps: number) => string;
    logger?: Logger;
  }

  interface DistributionStrategyOptions {
    /** Har bir turning ulushi (yig'indisi 1 bo'lishi kerak). */
    distribution?: number[];
    /** Ikki amal orasidagi kutish, ms. */
    delay?: number;
    /** Jami amallar soni. */
    nb?: number;
  }

  export const species: {
    clicker: (options?: ClickerOptions) => SpeciesFactory;
    toucher: (options?: ToucherOptions) => SpeciesFactory;
    formFiller: (options?: FormFillerOptions) => SpeciesFactory;
    scroller: (options?: ScrollerOptions) => SpeciesFactory;
    typer: (options?: TyperOptions) => SpeciesFactory;
  };

  export const mogwais: {
    /** `alert`/`confirm`/`prompt` ni ushlab qoladi — modal oyna hujumni to'xtatib qo'ymaydi. */
    alert: () => MogwaiFactory;
    fps: (options?: FpsOptions) => MogwaiFactory;
    gizmo: (options?: GizmoOptions) => MogwaiFactory;
  };

  export const strategies: {
    allTogether: (options?: { delay?: number; nb?: number }) => StrategyFactory;
    bySpecies: (options?: { delay?: number; nb?: number }) => StrategyFactory;
    distribution: (options?: DistributionStrategyOptions) => StrategyFactory;
  };

  export const allSpecies: SpeciesFactory[];
  export const allMogwais: MogwaiFactory[];

  export interface Horde {
    /** Hujumni boshlaydi; barcha amallar tugaganda `resolve` bo'ladi. */
    unleash: () => Promise<void>;
    /** Hujumni yarmida to'xtatadi. */
    stop: () => void;
  }

  export function createHorde(options?: {
    species?: SpeciesFactory[];
    mogwais?: MogwaiFactory[];
    strategies?: StrategyFactory[];
    logger?: Logger;
    randomizer?: unknown;
    window?: Window;
  }): Horde;
}
