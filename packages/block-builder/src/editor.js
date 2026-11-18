/**
 * The script a host loads to open the reference editor: it registers `<block-builder>`, which then opens itself.
 */
import { defineBlockBuilder } from './builder-element.js';

defineBlockBuilder();
