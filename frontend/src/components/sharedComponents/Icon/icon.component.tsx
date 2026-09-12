import type { FC } from 'react';
import { Icon as SharedIcon, IconProps } from '@web-revizor/ui-kit/components/Icon';
import { SpritesMap } from './sprite-info';

export type SpriteKey = {
  [Key in keyof SpritesMap]: `${Key}/${SpritesMap[Key]}`;
}[keyof SpritesMap];

export const Icon = SharedIcon as FC<IconProps<SpriteKey>>;
