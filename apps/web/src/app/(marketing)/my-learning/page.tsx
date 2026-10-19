import type { Metadata } from "next";
import { MyLearning } from "./my-learning";

export const metadata: Metadata = { title: "My learning", robots: { index: false } };

export default function MyLearningPage() {
  return <MyLearning />;
}
