export type PageTreeNode = {
  id: number;
  parentId: number | null;
  title: string;
  uri: string;
  children: PageTreeNode[];
};
